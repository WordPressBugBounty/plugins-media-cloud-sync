import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link } from 'react-router-dom';
import { toast } from 'react-toastify';

import style from './style.scss';
import {
	ClassNames,
	fetchWP,
	getApiClient,
	formatPercent,
	jobPercentage,
	isPro,
	getActiveSyncMode,
	resolveSyncMethod
} from '../../helper';
import getSchema from '../../schema';
import { applyFilters } from '../../helper/extend';
import { setSyncData, setSyncStatus } from '../../redux/slices/sync';
import { setCounts } from '../../redux/slices/common';
import InfoBox from '../../components/SyncScreen/InfoBox';
import PremiumCard from '../../components/SyncScreen/PremiumCard';
import ErrorListing from '../../components/SyncScreen/ErrorListing';
import SyncMethodToggle from '../../components/SyncScreen/SyncMethodToggle';
import ConfirmDialog from '../../components/ConfirmDialog';
import SchemaOptions from '../../components/SchemaOptions';
import Spinner from '../../components/Spinner';
import BackLink from '../../components/BackLink';

const defaultBeforeStart = async () => true;

const optionDefaults = (confirm) =>
	Object.fromEntries(
		(confirm?.options || []).map((option) => [
			option.name,
			option.default ?? false
		])
	);

export default function SyncScreen({
	action,
	beforeStart,
	showBackLink = true,
	// Pages with their own header hide the job's title and description.
	showHeading = true,
	startOptions = {}
}) {
	const dispatch = useDispatch();
	const syncScreenSettings = getSchema('syncScreen');
	const currentScreen = syncScreenSettings?.[action];
	const confirm = currentScreen?.confirm;

	// Schema `confirm` block: dialog before START whose toggle/text/number/select options are sent as start options.
	const [confirmOpen, setConfirmOpen] = React.useState(false);
	const [confirmValues, setConfirmValues] = React.useState(() =>
		optionDefaults(confirm)
	);
	const confirmResolveRef = React.useRef(null);
	// handleStart awaits the dialog, so it must read values set during it from a ref, not stale state.
	const confirmValuesRef = React.useRef(confirmValues);
	confirmValuesRef.current = confirmValues;

	const openConfirm = React.useCallback(
		() =>
			new Promise((resolve) => {
				confirmResolveRef.current = resolve;
				setConfirmValues(optionDefaults(confirm));
				setConfirmOpen(true);
			}),
		[confirm]
	);

	// Schema `confirm.count`: endpoint answering how many items the chosen options match.
	const [confirmCount, setConfirmCount] = React.useState('');
	React.useEffect(() => {
		if (!confirmOpen || !confirm?.count?.endpoint) return undefined;
		let current = true;
		setConfirmCount(null);
		const timer = setTimeout(async () => {
			try {
				const client = getApiClient(confirm.count.api);
				const response = await client.post(confirm.count.endpoint, { options: confirmValues });
				if (current) setConfirmCount(response?.message || '');
			} catch (error) {
				if (current) setConfirmCount('');
			}
		}, 400);
		return () => {
			current = false;
			clearTimeout(timer);
		};
	}, [confirmOpen, confirmValues, confirm?.count?.endpoint, confirm?.count?.api]);

	const closeConfirm = (result) => {
		setConfirmOpen(false);
		const resolve = confirmResolveRef.current;
		confirmResolveRef.current = null;
		resolve?.(result);
	};

	const runBeforeStart =
		beforeStart || (confirm ? openConfirm : defaultBeforeStart);
	const allSyncStatistics = useSelector((state) => state?.sync);
	const { settings, counts } = useSelector((state) => state?.common);
	const { service } = useSelector((state) => state?.credentials);

	const syncStatus = React.useMemo(
		() => allSyncStatistics?.[action] || {},
		[allSyncStatistics, action]
	);

	const [activeButtons, setActiveButtons] = React.useState(['start']);
	const [loading, setLoading] = React.useState('start');
	const [hasOtherSyncRunning, setHasOtherSyncRunning] = React.useState(false);
	React.useEffect(() => {
		// Check if any other sync is running
		const otherActions = Object.keys(allSyncStatistics || {}).filter(
			(key) => key !== action
		);
		const isOtherRunning = otherActions.some(
			(key) =>
				allSyncStatistics[key]?.status === 'running' ||
				allSyncStatistics[key]?.status === 'paused'
		);
		setHasOtherSyncRunning(isOtherRunning);
	}, [allSyncStatistics, action]);

	const [progress, setProgress] = React.useState({
		total: 0,
		processed: 0,
		failed: 0,
		remaining: 0,
		percentage: 0,
		status: 'stopped',
		last_run: 0,
		pause_requested: false,
		stop_requested: false
	});

	React.useEffect(() => {
		async function fetchStatus() {
			const response = await fetchWP.get('sync/status');
			if (response?.success) {
				if (response?.status) {
					dispatch(setSyncData(response?.status));
				}

				if (response?.counts) {
					// Update counts if provided
					dispatch(setCounts(response.counts));
				}
			} else {
				toast.error(response.message, {
					autoClose: 4000
				});
			}
		}
		fetchStatus();
	}, []);

	React.useEffect(() => {
		setProgress({
			...syncStatus,
			total: syncStatus?.total,
			remaining: syncStatus?.remaining
		});

		const buttons = [];
		if (syncStatus?.status === 'running') {
			buttons.push('pause');
			buttons.push('stop');
		}

		if (syncStatus?.status === 'paused') {
			buttons.push('stop');
			buttons.push('resume');
		}

		if (
			syncStatus?.status === 'stopped' ||
			syncStatus?.status === 'completed'
		) {
			buttons.push('start');
		}
		setActiveButtons(buttons);

		if (syncStatus?.pause_requested) {
			setLoading('pause');
		} else if (syncStatus?.stop_requested) {
			setLoading('stop');
		} else {
			setLoading(false);
		}
	}, [syncStatus]);

	// Locally staged choice — deliberately NOT saved on every click. Saving (and thus
	// actually taking effect) happens on Start, so picking a mode is a single decisive
	// action rather than a silent background change the user has to trust already applied.
	const [localSyncMethod, setLocalSyncMethod] = React.useState(() =>
		resolveSyncMethod(syncStatus?.sync_method)
	);

	// Once a sync is actually active, the (now-disabled) toggle must reflect what's truly
	// driving it — the saved setting — not a stale unsaved pick from before it started.
	React.useEffect(() => {
		if (progress?.status === 'running' || progress?.status === 'paused') {
			setLocalSyncMethod(resolveSyncMethod(progress?.sync_method));
		}
	}, [progress?.status, progress?.sync_method]);

	// What's driving this action now (saved method, not the staged pick); display only — any Ajax ticking is an add-on's (app.root slot).
	const activeMode = getActiveSyncMode(progress);

	// Jobs whose real total is only known after start() (schema `requiresKnownTotal: false`)
	// keep Start available even when the pre-run count is 0.
	const requiresKnownTotal = currentScreen?.requiresKnownTotal !== false;

	// Succeeded + Failed + Remaining always add up to the total; a stopped job is idle, so all of it remains.
	const isIdle = progress?.status === 'stopped';
	const runTotal = Math.max(0, progress?.total || 0);
	const remaining = isIdle ? runTotal : Math.min(runTotal, Math.max(0, progress?.remaining || 0));
	const failed = isIdle ? 0 : Math.min(runTotal - remaining, Math.max(0, progress?.failed || 0));
	const succeeded = runTotal - remaining - failed;

	// Last run's result, kept after the runner resets to idle until the next Start (or leaving the page).
	const [lastRun, setLastRun] = React.useState(null);
	const activeSnapshot = React.useRef(null);
	React.useEffect(() => {
		const current = syncStatus?.status;
		const snapshot = (source) => {
			const total = Math.max(0, source?.total || 0);
			const done = current === 'completed' ? total : Math.min(total, Math.max(0, source?.processed || 0));
			const failedCount = Math.min(done, Math.max(0, source?.failed || 0));
			return { total, succeeded: done - failedCount, failed: failedCount, remaining: total - done };
		};
		if (current === 'running' || current === 'paused') {
			activeSnapshot.current = snapshot(syncStatus);
		} else if (current === 'completed') {
			setLastRun({ outcome: 'completed', ...snapshot(syncStatus) });
			activeSnapshot.current = null;
		} else if (current === 'stopped' && activeSnapshot.current) {
			setLastRun({ outcome: 'stopped', ...activeSnapshot.current });
			activeSnapshot.current = null;
		}
	}, [syncStatus]);
	const showResult = !!lastRun && (progress?.status === 'stopped' || progress?.status === 'completed');

	// Labels come from the schema's `counters`; each `value` names one of these numbers.
	const libraryTotal = counts?.all?.total || 0;
	const librarySynced = Math.min(libraryTotal, counts?.all?.uploaded || 0);
	const percentage =
		showResult && !currentScreen?.libraryProgress
			? lastRun.total
				? ((lastRun.total - lastRun.remaining) * 100) / lastRun.total
				: 100
			: jobPercentage(currentScreen, progress, counts);
	const counterValues = {
		libraryTotal,
		librarySynced,
		libraryPending: libraryTotal - librarySynced,
		runTotal: showResult ? lastRun.total : runTotal,
		succeeded: showResult ? lastRun.succeeded : succeeded,
		failed: showResult ? lastRun.failed : failed,
		remaining: showResult ? lastRun.remaining : remaining
	};
	const counters = (currentScreen?.counters || []).filter(
		(counter) => counter?.value in counterValues
	);

	const handleSyncMethodChange = (value) => {
		setLocalSyncMethod(value);
	};

	const handleStart = async () => {
		const shouldStart = await runBeforeStart();
		if (!shouldStart) return;
		setLastRun(null);

		setLoading('start');

		// Save a changed method (an add-on's job; free only runs cron) before starting, so the first tick uses it.
		if (localSyncMethod !== (progress?.sync_method || 'cron')) {
			const saved = await Promise.resolve(
				applyFilters('wpmcs.syncMethod.save', null, { action, syncMethod: localSyncMethod })
			).catch(() => ({ success: false }));

			if (saved && !saved.success) {
				toast.error(
					wp.i18n.__(
						'Failed to save the sync method. Please try again.',
						'media-cloud-sync'
					),
					{ autoClose: 4000 }
				);
				setLoading(false);
				return;
			}

			if (saved?.status) {
				dispatch(
					setSyncStatus({ key: action, value: saved.status })
				);
			}
		}

		await fetchWP
			.post('sync/start', {
				action,
				options: confirm
					? { ...startOptions, ...confirmValuesRef.current }
					: startOptions
			})
			.then((response) => {
				if (response.success) {
					if (response?.status) {
						dispatch(
							setSyncStatus({
								key: action,
								value: { ...response.status }
							})
						);
					}
					if (response?.notice) {
						toast.success(response.notice, { autoClose: 5000 });
					}
				} else {
					toast.error(response.message, {
						autoClose: 4000
					});
				}
				setLoading(false);
			})
			.catch(() => {
				toast.error(
					wp.i18n.__(
						'Something went wrong. Please try again.',
						'media-cloud-sync'
					),
					{ autoClose: 4000 }
				);
				setLoading(false);
			});
	};

	const handlePause = async () => {
		setLoading('pause');
		await fetchWP
			.post('sync/pause', {
				action
			})
			.then((response) => {
				if (response.success) {
					if (response?.status) {
						dispatch(
							setSyncStatus({
								key: action,
								value: { ...response.status }
							})
						);
					}
				} else {
					toast.error(response.message, {
						autoClose: 4000
					});
				}
				setLoading(false);
			})
			.catch(() => {
				toast.error(
					wp.i18n.__(
						'Something went wrong. Please try again.',
						'media-cloud-sync'
					),
					{ autoClose: 4000 }
				);
				setLoading(false);
			});
	};

	const handleResume = async () => {
		setLoading('resume');
		await fetchWP
			.post('sync/resume', {
				action
			})
			.then((response) => {
				if (response.success) {
					if (response?.status) {
						dispatch(
							setSyncStatus({
								key: action,
								value: { ...response.status }
							})
						);
					}
				} else {
					toast.error(response.message, {
						autoClose: 4000
					});
				}
				setLoading(false);
			})
			.catch(() => {
				toast.error(
					wp.i18n.__(
						'Something went wrong. Please try again.',
						'media-cloud-sync'
					),
					{ autoClose: 4000 }
				);
				setLoading(false);
			});
	};

	// Jobs that produce a file report download: { url } in their status; POSTing to url returns a one-time
	// file link, which the browser opens itself so large files are saved straight to disk.
	const [downloading, setDownloading] = React.useState(false);
	const handleDownload = async () => {
		setDownloading(true);
		let response = null;
		try {
			response = await fetch(progress.download.url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': window.wpmcs_object.api_nonce }
			}).then((res) => res.json());
		} catch (error) {
			response = null;
		}
		if (response?.success && response?.url) {
			const link = document.createElement('a');
			link.href = response.url;
			document.body.appendChild(link);
			link.click();
			link.remove();
		} else {
			toast.error(
				response?.message || wp.i18n.__('Unable to download the file.', 'media-cloud-sync'),
				{ autoClose: 4000 }
			);
		}
		setDownloading(false);
	};

	const handleStop = async () => {
		setLoading('stop');
		await fetchWP
			.post('sync/stop', {
				action
			})
			.then((response) => {
				if (response.success) {
					if (response?.status) {
						dispatch(
							setSyncStatus({
								key: action,
								value: { ...response.status }
							})
						);
					}

					if (response?.counts) {
						// Update counts if provided
						dispatch(setCounts(response.counts));
					}
				} else {
					toast.error(response.message, {
						autoClose: 4000
					});
				}
				setLoading(false);
			})
			.catch(() => {
				toast.error(
					wp.i18n.__(
						'Something went wrong. Please try again.',
						'media-cloud-sync'
					),
					{ autoClose: 4000 }
				);
				setLoading(false);
			});
	};

	// Status pill, progress line and action buttons for the hero card.
	const status = progress?.status || 'stopped';
	const statusInfo = (() => {
		if (showResult) {
			return lastRun.outcome === 'completed'
				? { tone: 'success', label: wp.i18n.__('Completed', 'media-cloud-sync') }
				: { tone: 'idle', label: wp.i18n.__('Stopped', 'media-cloud-sync') };
		}
		if (status === 'running' && (progress?.pause_requested || progress?.stop_requested)) {
			return { tone: 'warning', label: progress?.stop_requested ? wp.i18n.__('Stopping…', 'media-cloud-sync') : wp.i18n.__('Pausing…', 'media-cloud-sync') };
		}
		if (status === 'running') return { tone: 'running', label: wp.i18n.__('Running', 'media-cloud-sync') };
		if (status === 'paused') return { tone: 'warning', label: wp.i18n.__('Paused', 'media-cloud-sync') };
		if (status === 'completed') return { tone: 'success', label: wp.i18n.__('Completed', 'media-cloud-sync') };
		return { tone: 'idle', label: wp.i18n.__('Ready', 'media-cloud-sync') };
	})();
	const progressLine = showResult && !currentScreen?.libraryProgress
		? wp.i18n.sprintf(
				/* translators: 1: succeeded items, 2: failed items */
				wp.i18n.__('%1$s succeeded, %2$s failed', 'media-cloud-sync'),
				lastRun.succeeded.toLocaleString('en-US'),
				lastRun.failed.toLocaleString('en-US')
			)
		: currentScreen?.libraryProgress
		? wp.i18n.sprintf(
				/* translators: 1: synced media, 2: total media */
				wp.i18n.__('%1$s of %2$s media synced', 'media-cloud-sync'),
				librarySynced.toLocaleString('en-US'),
				libraryTotal.toLocaleString('en-US')
			)
		: isIdle && progress?.idle_message
			? progress.idle_message
		: isIdle
			? wp.i18n.sprintf(
					/* translators: %s: number of items */
					wp.i18n.__('%s items ready to process', 'media-cloud-sync'),
					runTotal.toLocaleString('en-US')
				)
			: wp.i18n.sprintf(
					/* translators: 1: processed items, 2: items in this run */
					wp.i18n.__('%1$s of %2$s processed', 'media-cloud-sync'),
					(succeeded + failed).toLocaleString('en-US'),
					runTotal.toLocaleString('en-US')
				);
	const buttonConfig = {
		start: { label: showResult ? wp.i18n.__('Run again', 'media-cloud-sync') : wp.i18n.__('Start', 'media-cloud-sync'), tone: style.primary, onClick: handleStart },
		resume: { label: wp.i18n.__('Resume', 'media-cloud-sync'), tone: style.primary, onClick: handleResume },
		pause: { label: wp.i18n.__('Pause', 'media-cloud-sync'), tone: style.warning, onClick: handlePause },
		stop: { label: wp.i18n.__('Stop', 'media-cloud-sync'), tone: style.danger, onClick: handleStop }
	};
	const canRun =
		(!requiresKnownTotal || (progress?.total || 0) > 0) &&
		settings?.copy_to_bucket &&
		service;
	// Tile accent per counter value.
	const tileTone = {
		succeeded: style.success,
		librarySynced: style.success,
		failed: style.danger,
		remaining: style.warning,
		libraryPending: style.warning
	};

	return (
		<div className={style.syncScreen}>
			{showBackLink ? (
				<BackLink
					to={currentScreen?.back?.path || '/'}
					label={currentScreen?.back?.label}
				/>
			) : null}

			<div className={style.content}>
				<div className={style.syncSection}>
					<InfoBox
						progress={progress}
						activeMode={activeMode}
						doneMessage={currentScreen?.doneMessage}
					/>

					<div
						className={ClassNames([
							style.jobColumn,
							isPro() ? style.fullWidth : null
						])}
					>
						{showHeading ? (
							<div className={style.jobHead}>
								<h2>
									{currentScreen?.title ||
										wp.i18n.__('Syncing', 'media-cloud-sync')}
								</h2>
								{currentScreen?.description ? (
									<p>{currentScreen?.description}</p>
								) : null}
							</div>
						) : null}

						{/* Status `summary`: the settings this job applies, read fresh with each status. */}
						{progress?.summary?.rows?.length ? (
							<div className={style.summaryBox}>
								<div className={style.summaryHead}>
									<h3>{progress.summary.title}</h3>
									{progress.summary.link ? (
										<Link to={progress.summary.link.path}>{progress.summary.link.label}</Link>
									) : null}
								</div>
								<dl>
									{progress.summary.rows.map((row) => (
										<React.Fragment key={row.label}>
											<dt>{row.label}</dt>
											<dd>
												<code>{row.value}</code>
												{row.note ? <span className={ClassNames([style.summaryNote, style[row.tone] || null])}>{row.note}</span> : null}
											</dd>
										</React.Fragment>
									))}
								</dl>
							</div>
						) : null}

						<div className={style.jobGrid}>
							{/* Setup: the method is chosen before Start. */}
							<div className={ClassNames([style.jobCard, style.setupCard])}>
								<div className={style.stepTitle}>
									<span className={ClassNames([style.stepBadge, style.current])}>1</span>
									{wp.i18n.__('Choose how it runs', 'media-cloud-sync')}
								</div>
								<SyncMethodToggle
									value={localSyncMethod}
									onChange={handleSyncMethodChange}
									disabled={
										status === 'running' ||
										status === 'paused' ||
										hasOtherSyncRunning ||
										!!loading
									}
									allowed={currentScreen?.syncMethods}
									showLabel={false}
								/>

								<div className={style.stepTitle}>
									<span className={style.stepBadge}>2</span>
									{status === 'running' || status === 'paused'
										? wp.i18n.__('Control the run', 'media-cloud-sync')
										: wp.i18n.__('Start', 'media-cloud-sync')}
								</div>
								{canRun ? (
									<>
										{hasOtherSyncRunning ? (
											<div className={style.alert}>
												{wp.i18n.__(
													'Another sync process is currently in progress. Please make sure that is finished or stop it before starting a new one.',
													'media-cloud-sync'
												)}
											</div>
										) : null}
										{window.wpmcs_object.is_cron_disabled && activeMode !== 'ajax' ? (
											<div className={ClassNames([style.alert, style.warning])}>
												{wp.i18n.__(
													'WP-Cron is not enabled on your site. Please enable WP-Cron or configure a server-level cron job to ensure the sync feature operates correctly. Without an active cron process, the sync will not run even if it is initiated.',
													'media-cloud-sync'
												)}
											</div>
										) : null}
										{!hasOtherSyncRunning ? (
											<div className={style.actions}>
												{activeButtons?.map((button) => {
													const config = buttonConfig[button];
													if (!config) return null;
													return (
														<button
															key={button}
															type="button"
															className={ClassNames([
																style.jobButton,
																config.tone,
																loading === button ? style.loading : null
															])}
															disabled={!!loading}
															onClick={config.onClick}
														>
															{config.label}
															<span className={style.loader} />
														</button>
													);
												})}
											</div>
										) : null}
									</>
								) : (
									<p className={style.nothingToRun}>
										{wp.i18n.__('Nothing to process right now.', 'media-cloud-sync')}
									</p>
								)}
							</div>

							{/* Progress: status, ring and this job's counters. */}
							<div className={ClassNames([style.jobCard, style.progressCard])}>
								<span className={ClassNames([style.statusPill, style[statusInfo.tone]])}>
									<span className={style.dot} />
									{statusInfo.label}
								</span>
								<div
									className={ClassNames([
										style.ring,
										status === 'running' ? style.active : null
									])}
								>
									<svg viewBox="0 0 120 120" aria-label={wp.i18n.__('Progress', 'media-cloud-sync')}>
										<circle className={style.track} cx="60" cy="60" r="52" />
										{/* While running, a faint arc sweeps the track under the bar. */}
										{status === 'running' ? (
											<circle
												className={style.sweep}
												cx="60"
												cy="60"
												r="52"
												strokeDasharray="70 256.73"
											/>
										) : null}
										<circle
											className={style.bar}
											cx="60"
											cy="60"
											r="52"
											strokeDasharray="326.73"
											strokeDashoffset={326.73 - (percentage * 326.73) / 100}
										/>
										{/* And a glowing head at the bar's tip. */}
										{status === 'running' ? (
											<g
												className={style.head}
												style={{ transform: `rotate(${(percentage * 360) / 100}deg)` }}
											>
												<circle className={style.halo} cx="112" cy="60" r="4.5" />
												<circle className={style.tip} cx="112" cy="60" r="4.5" />
											</g>
										) : null}
									</svg>
									<div className={style.ringValue}>
										<span className={style.ringPercent}>
											{formatPercent(percentage)}
											<small>%</small>
										</span>
									</div>
								</div>
								<div className={style.progressLine}>{progressLine}</div>
								{isIdle && progress?.idle_hint ? (
									<p className={style.progressHint}>{progress.idle_hint}</p>
								) : null}
								<div className={style.statTiles}>
									{counters.map(({ label, value }, index) => (
										<div
											className={ClassNames([style.statTile, tileTone[value] || null])}
											key={`${index}-${value}`}
										>
											<span className={style.statValue}>
												{counterValues[value].toLocaleString('en-US')}
											</span>
											<span className={style.statLabel}>{label}</span>
										</div>
									))}
								</div>
								{progress?.download?.url ? (
									<button
										type="button"
										className={ClassNames([
											style.jobButton,
											style.primary,
											style.downloadButton,
											downloading ? style.loading : null
										])}
										disabled={downloading}
										onClick={handleDownload}
									>
										{currentScreen?.downloadLabel || wp.i18n.__('Download', 'media-cloud-sync')}
										<span className={style.loader} />
									</button>
								) : null}
							</div>
						</div>
					</div>

					{!isPro() ? <PremiumCard /> : null}

					<ErrorListing
						action={action}
						retrySupported={currentScreen?.retry !== false}
						labels={currentScreen?.results || {}}
					/>
				</div>
			</div>
			{confirm ? (
				<ConfirmDialog
					open={confirmOpen}
					danger={!!confirm.danger}
					title={confirm.title}
					message={confirm.message}
					confirmLabel={confirm.confirmLabel}
					onCancel={() => closeConfirm(false)}
					onConfirm={() => closeConfirm(true)}
				>
					<SchemaOptions
						options={confirm.options}
						values={confirmValues}
						idPrefix={action}
						onChange={(name, value) =>
							setConfirmValues((values) => ({ ...values, [name]: value }))
						}
					/>
					{confirm.count && confirmCount !== '' ? (
						<div className={style.confirmCount} aria-live="polite">
							{confirmCount === null ? <Spinner size={18} text={wp.i18n.__('Counting matching media', 'media-cloud-sync')} /> : confirmCount}
						</div>
					) : null}
				</ConfirmDialog>
			) : null}
		</div>
	);
}
