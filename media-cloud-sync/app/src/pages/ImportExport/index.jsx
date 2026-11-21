import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { toast } from 'react-toastify';

import style from './style.scss';
import getSchema from '../../schema';
import { fetchWP } from '../../helper';
import { setSettingsData } from '../../redux/slices/common';
import { PrimaryButton, SecondaryButton } from '../../components/Buttons';
import BackLink from '../../components/BackLink';

const failed = (message) =>
	toast.error(message || wp.i18n.__('Something went wrong. Please try again.', 'media-cloud-sync'), { autoClose: 6000 });

// Export this site's settings as a JSON file, or import one with a preview of the changes.
export default function ImportExport() {
	const page = getSchema('importExportPage') || {};
	const dispatch = useDispatch();
	const { service, serviceEnabled } = useSelector((state) => state?.credentials);
	const syncs = useSelector((state) => state?.sync);
	const fileRef = React.useRef(null);
	const [busy, setBusy] = React.useState('');
	const [preview, setPreview] = React.useState(null);

	const operational = !!service && serviceEnabled !== false;
	const jobRunning = Object.values(syncs || {}).some(
		(sync) => sync?.status === 'running' || sync?.status === 'paused'
	);
	const importNote = !operational ? page.importOffline : jobRunning ? page.importBusy : '';

	const exportSettings = async () => {
		setBusy('export');
		try {
			const response = await fetchWP.get('settings/export');
			if (!response?.success) {
				failed(response?.message);
				return;
			}
			let host = 'site';
			try {
				host = new URL(response.data.site).host;
			} catch (error) {
				// Keep the generic name.
			}
			const link = document.createElement('a');
			link.href = URL.createObjectURL(
				new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' })
			);
			link.download = `media-cloud-sync-settings-${host}-${new Date().toISOString().slice(0, 10)}.json`;
			document.body.appendChild(link);
			link.click();
			link.remove();
			setTimeout(() => URL.revokeObjectURL(link.href), 1000);
		} catch (error) {
			failed(error?.message);
		} finally {
			setBusy('');
		}
	};

	const readFile = (event) => {
		const file = event.target.files?.[0];
		event.target.value = '';
		if (!file) return;
		const reader = new FileReader();
		reader.onload = async () => {
			let data;
			try {
				data = JSON.parse(String(reader.result));
			} catch (error) {
				failed(page.invalidJson);
				return;
			}
			setBusy('import');
			try {
				const response = await fetchWP.post('settings/import', { data, apply: false });
				if (response?.success) {
					setPreview({ name: file.name, data, changes: response.changes || [], warnings: response.warnings || [] });
				} else {
					failed(response?.message);
				}
			} catch (error) {
				failed(error?.message);
			} finally {
				setBusy('');
			}
		};
		reader.readAsText(file);
	};

	const apply = async () => {
		setBusy('apply');
		try {
			const response = await fetchWP.post('settings/import', { data: preview.data, apply: true });
			if (!response?.success) {
				failed(response?.message);
				return;
			}
			toast.success(response.message, { autoClose: 4000 });
			setPreview(null);
			if (response.reload) {
				// The delivery provider changed: the page reloads its provider data.
				setTimeout(() => window.location.reload(), 1200);
			} else if (response.settings) {
				dispatch(setSettingsData(response.settings));
			}
		} catch (error) {
			failed(error?.message);
		} finally {
			setBusy('');
		}
	};

	return (
		<div className={style.importExport}>
			<BackLink />
			<div className={style.head}>
				<h2>{page.title}</h2>
				{page.description ? <p>{page.description}</p> : null}
			</div>

			<div className={style.grid}>
				<section className={style.card}>
					<h3>{page.exportTitle}</h3>
					<p>{page.exportText}</p>
					<div className={style.actions}>
						<SecondaryButton
							type="button"
							label={page.exportButton}
							loading={busy === 'export'}
							disabled={!!busy}
							onClick={exportSettings}
						/>
					</div>
				</section>

				<section className={style.card}>
					<h3>{page.importTitle}</h3>
					<p>{page.importText}</p>

					{preview ? (
						<div className={style.preview}>
							<div className={style.fileName}>{preview.name}</div>
							{preview.warnings.length ? (
								<ul className={style.warnings}>
									{preview.warnings.map((warning) => (
										<li key={warning}>{warning}</li>
									))}
								</ul>
							) : null}
							{preview.changes.length ? (
								<>
									<h4>{page.previewTitle}</h4>
									<ul className={style.changes}>
										{preview.changes.map((change, index) => (
											<li key={`${change.label}-${index}`}>
												<span className={style.changeLabel}>{change.label}</span>
												<span className={style.changeValues}>
													<span className={style.from}>{change.from}</span>
													<span aria-hidden="true">→</span>
													<span className={style.to}>{change.to}</span>
												</span>
												{change.warning ? (
													<span className={style.changeWarning}>{change.warning}</span>
												) : null}
											</li>
										))}
									</ul>
								</>
							) : (
								<p className={style.nothing}>{page.nothing}</p>
							)}
							<div className={style.actions}>
								{preview.changes.length ? (
									<PrimaryButton
										type="button"
										label={page.apply}
										loading={busy === 'apply'}
										disabled={!!busy || !!importNote}
										onClick={apply}
									/>
								) : null}
								<SecondaryButton
									type="button"
									label={page.cancel}
									disabled={busy === 'apply'}
									onClick={() => setPreview(null)}
								/>
							</div>
						</div>
					) : (
						<div className={style.actions}>
							<SecondaryButton
								type="button"
								label={page.importButton}
								loading={busy === 'import'}
								disabled={!!busy || !!importNote}
								onClick={() => fileRef.current?.click()}
							/>
						</div>
					)}
					{importNote ? <p className={style.note}>{importNote}</p> : null}
					<input ref={fileRef} type="file" accept=".json,application/json" hidden onChange={readFile} />
				</section>
			</div>
		</div>
	);
}
