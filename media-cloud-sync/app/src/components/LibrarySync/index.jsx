import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { Link } from 'react-router-dom';
import { toast } from 'react-toastify';

import style from './style.scss';
import { ClassNames, fetchWP, formatPercent } from '../../helper';
import { setCounts } from '../../redux/slices/common';
import getSchema from '../../schema';

const percentOf = (done, total) => (total ? (done * 100) / total : 0);

// Library-wide sync progress, broken down by source, with Refresh and Sync now.
export default function LibrarySync() {
	const dispatch = useDispatch();
	const { counts } = useSelector((state) => state?.common);
	const { service, serviceEnabled } = useSelector((state) => state?.credentials);
	const syncPath = getSchema('syncScreen')?.sync_to_cloud?.path || '/sync-to-cloud';
	const [loading, setLoading] = React.useState(false);

	const total = counts?.all?.total || 0;
	const synced = counts?.all?.uploaded || 0;
	const pending = Math.max(total - synced, 0);
	const percent = percentOf(synced, total);
	const sources = Object.keys(counts?.categorized || {});
	const operational = !!service && serviceEnabled !== false;

	const handleRefresh = () => {
		if (loading) return;
		setLoading(true);
		const refresh = fetchWP.post('refreshCounts').then((response) => {
			if (response.success) {
				if (response?.counts) {
					dispatch(setCounts(response.counts));
				}
				toast.success(response.message, { autoClose: 2000 });
			} else {
				toast.error(response.message, { autoClose: 4000 });
			}
		});
		toast
			.promise(refresh, {
				pending: wp.i18n.__('Refreshing Counts', 'media-cloud-sync')
			})
			.finally(() => setLoading(false));
	};

	return (
		<section className={style.card}>
			<div className={style.head}>
				<h2>
					{getSchema('dashboard')?.syncTitle ||
						wp.i18n.__('Library sync', 'media-cloud-sync')}
				</h2>
				<button
					type="button"
					className={ClassNames([style.refresh, loading ? style.loading : null])}
					onClick={handleRefresh}
					disabled={loading}
				>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
						<path
							d="M20 12a8 8 0 1 1-2.34-5.66M20 4v4.5h-4.5"
							stroke="currentColor"
							strokeWidth="2"
							strokeLinecap="round"
							strokeLinejoin="round"
						/>
					</svg>
					{wp.i18n.__('Refresh', 'media-cloud-sync')}
				</button>
			</div>

			<div className={style.summary}>
				<span className={style.percent}>{formatPercent(percent)}%</span>
				<span className={style.summaryText}>
					{wp.i18n.sprintf(
						/* translators: 1: synced count, 2: total count */
						wp.i18n.__('%1$s of %2$s media files are in the cloud', 'media-cloud-sync'),
						synced.toLocaleString('en-US'),
						total.toLocaleString('en-US')
					)}
				</span>
			</div>

			<div className={style.bar} aria-hidden="true">
				<span className={style.barSynced} style={{ width: `${percent}%` }} />
				<span className={style.barPending} style={{ width: `${100 - percent}%` }} />
			</div>

			<div className={style.legend}>
				<span>
					<i className={style.keySynced} />
					{wp.i18n.__('Synced', 'media-cloud-sync')}{' '}
					<strong>{synced.toLocaleString('en-US')}</strong>
				</span>
				<span>
					<i className={style.keyPending} />
					{wp.i18n.__('Not synced', 'media-cloud-sync')}{' '}
					<strong>{pending.toLocaleString('en-US')}</strong>
				</span>
				<span>
					{wp.i18n.__('Total', 'media-cloud-sync')}{' '}
					<strong>{total.toLocaleString('en-US')}</strong>
				</span>
			</div>

			{sources.length ? (
				<div className={style.tableWrap}>
					<table className={style.sources}>
						<thead>
							<tr>
								<th>{wp.i18n.__('By source', 'media-cloud-sync')}</th>
								<th className={style.progressCol}>
									{wp.i18n.__('Progress', 'media-cloud-sync')}
								</th>
								<th className={style.num}>{wp.i18n.__('Synced', 'media-cloud-sync')}</th>
								<th className={style.num}>{wp.i18n.__('Total', 'media-cloud-sync')}</th>
								<th className={style.num}>{wp.i18n.__('Not synced', 'media-cloud-sync')}</th>
							</tr>
						</thead>
						<tbody>
							{sources.map((key) => {
								const row = counts.categorized[key] || {};
								const rowTotal = row.total || 0;
								const rowSynced = row.uploaded || 0;
								const rowPending = Math.max(rowTotal - rowSynced, 0);
								const rowPercent = percentOf(rowSynced, rowTotal);
								return (
									<tr key={key}>
										<th scope="row">{row.label || key}</th>
										<td>
											<span className={style.mini}>
												<span className={style.miniTrack}>
													<span style={{ width: `${rowPercent}%` }} />
												</span>
												<span className={style.miniValue}>
													{Math.round(rowPercent)}%
												</span>
											</span>
										</td>
										<td className={ClassNames([style.num, style.good])}>
											{rowSynced.toLocaleString('en-US')}
										</td>
										<td className={style.num}>{rowTotal.toLocaleString('en-US')}</td>
										<td
											className={ClassNames([
												style.num,
												rowPending ? style.warn : style.muted
											])}
										>
											{rowPending.toLocaleString('en-US')}
										</td>
									</tr>
								);
							})}
						</tbody>
					</table>
				</div>
			) : null}

			{operational ? (
				pending ? (
					<div className={style.cta}>
						<span>
							{wp.i18n.sprintf(
								/* translators: %s: number of files not synced yet */
								wp.i18n._n(
									'%s file is waiting to be uploaded.',
									'%s files are waiting to be uploaded.',
									pending,
									'media-cloud-sync'
								),
								pending.toLocaleString('en-US')
							)}
						</span>
						<Link to={syncPath} className={style.ctaButton}>
							{wp.i18n.__('Sync now', 'media-cloud-sync')}
						</Link>
					</div>
				) : total ? (
					<div className={ClassNames([style.cta, style.done])}>
						<span>{wp.i18n.__('All your media is in the cloud.', 'media-cloud-sync')}</span>
					</div>
				) : null
			) : null}
		</section>
	);
}
