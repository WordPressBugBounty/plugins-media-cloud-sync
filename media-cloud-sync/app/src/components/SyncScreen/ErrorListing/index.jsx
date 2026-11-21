import React from 'react';
import { useSelector } from 'react-redux';
import { toast } from 'react-toastify';

import style from './style.scss';
import { ClassNames, fetchWP } from '../../../helper';
import loaderBlue from '../../../images/loader-blue.svg';

// retrySupported comes from the job's schema `retry` flag — some jobs can't retry a single item.
// labels comes from the job's schema `results`, for jobs whose list isn't errors (e.g. files found by a scan).
export default function ErrorListing({ action, retrySupported = true, labels = {} }) {
	const [loading, setLoading] = React.useState([]);
	const [clearingLogs, setClearingLogs] = React.useState(false);
	const [errors, setErrors] = React.useState({
		loading: true,
		logs: {
			total_pages: 0,
			total: 1,
			page: 1,
			logs: []
		}
	});

	const mediaLink = (sourceType, id) => {
		if (labels.link === false) return null;
		switch (sourceType) {
			case 'media_library':
				return `${window.wpmcs_object.admin_url.replace(/\/$/, '')}/upload.php?item=${id}`;
			default:
				return id;
		}
	};

	const fetchLogs = async (page = 1, perPage = 10) => {
		setErrors({ ...errors, loading: true });
		const logResponse = await fetchWP.post('getErrorLogs', {
			type: action,
			page,
			per_page: perPage
		});

		setErrors({
			loading: false,
			logs: logResponse?.success
				? { ...logResponse?.logs }
				: {
						total_pages: 0,
						total: 1,
						page: 1,
						logs: []
				  }
		});
	};

	// Load on open, even mid-run, and reload when a run ends so its errors show without leaving the page.
	const jobStatus = useSelector((state) => state?.sync?.[action]?.status);
	const logsLoaded = React.useRef(false);
	React.useEffect(() => {
		if (!logsLoaded.current || !jobStatus || jobStatus === 'completed' || jobStatus === 'stopped') {
			logsLoaded.current = true;
			fetchLogs();
		}
	}, [jobStatus]);

	const retrySync = async ({
		media_id: mediaId,
		source_type: sourceType,
		type
	}) => {
		setLoading((prev) => [...prev, `${type}|${mediaId}|${sourceType}`]);
		let response;
		try {
			response = await fetchWP.post('sync/retrySingle', {
				id: mediaId,
				source_type: sourceType,
				type
			});
		} catch (error) {
			response = { success: false, message: error?.message };
		}

		if (response?.success) {
			toast.success(response.message, {
				autoClose: 2000
			});
			setErrors((prev) => ({
				...prev,
				logs: {
					...prev.logs,
					logs: prev.logs.logs.filter(
						(log) =>
							log?.media_id !== mediaId ||
							log?.source_type !== sourceType ||
							log?.type !== type
					)
				}
			}));
		} else {
			toast.error(
				response?.message || wp.i18n.__('Something went wrong. Please try again.', 'media-cloud-sync'),
				{ autoClose: 4000 }
			);
		}
		setLoading((prev) =>
			prev.filter((l) => l !== `${type}|${mediaId}|${sourceType}`)
		);
	};

	const handleClearLogs = async () => {
		setClearingLogs(true);
		const response = await fetchWP.post('clearErrorLogs', { type: action });
		if (response?.success) {
			toast.success(response.message, { autoClose: 2000 });
			fetchLogs(1);
		} else {
			toast.error(response?.message, { autoClose: 4000 });
		}
		setClearingLogs(false);
	};

	const { total_pages: totalPages, logs, page } = errors.logs;

	return (
		<div className={style.errorListing}>
			<div className={style.header}>
				<h2>{labels.title || wp.i18n.__('Errors', 'media-cloud-sync')}</h2>
				{logs.length > 0 ? (
					<button
						type="button"
						className={style.clearButton}
						onClick={handleClearLogs}
						disabled={clearingLogs}
					>
						{clearingLogs
							? wp.i18n.__('Clearing…', 'media-cloud-sync')
							: labels.clearLabel || wp.i18n.__('Clear Logs', 'media-cloud-sync')}
					</button>
				) : null}
			</div>
			<div className={style.table}>
				<table>
					<thead>
						<tr>
							<th>{labels.idLabel || wp.i18n.__('ID', 'media-cloud-sync')}</th>
							<th>{labels.messagesLabel || wp.i18n.__('Errors', 'media-cloud-sync')}</th>
							{retrySupported ? (
								<th className={style.actions}>
									{wp.i18n.__('Actions', 'media-cloud-sync')}
								</th>
							) : null}
						</tr>
					</thead>
					{errors.loading ? (
						<tbody>
							<tr>
								<td colSpan={retrySupported ? 3 : 2}>
									<div className={style.loader}>
										<img
											src={loaderBlue}
											alt={wp.i18n.__(
												'Loading',
												'media-cloud-sync'
											)}
										/>
									</div>
								</td>
							</tr>
						</tbody>
					) : (
						<tbody>
							{logs.length > 0 ? (
								logs.map((log) => (
									<tr
										key={
											(log?.media_id || '') +
											(log?.source_type || '') +
											(log?.type || '')
										}
									>
										<td>
											{labels.link === false ? (
												log?.media_id
											) : (
												<a
													href={mediaLink(
														log?.source_type,
														log?.media_id
													)}
													target="_blank"
													rel="noopener noreferrer"
												>
													{log?.media_id}
												</a>
											)}
										</td>
										<td className={style.errors}>
											{log?.logs?.length ? (
												<ul>
													{log?.logs?.map((error) => (
														<li key={error?.file}>
															<div
																className={
																	style.errorHeader
																}
															>
																<span>
																	{
																		error?.code
																	}
																</span>
																{error?.message}
															</div>
															{error?.file}
														</li>
													))}
												</ul>
											) : (
												wp.i18n.__(
													'No errors',
													'media-cloud-sync'
												)
											)}
										</td>
										{retrySupported ? (
											<td className={style.actions}>
												<button
													type="button"
													className={ClassNames([
														style.button,
														loading.includes(
															`${log?.type}|${log?.media_id}|${log?.source_type}`
														)
															? style.loading
															: null
													])}
													onClick={() => retrySync(log)}
													disabled={loading.includes(
														`${log?.type}|${log?.media_id}|${log?.source_type}`
													)}
												>
													{labels.retryLabel ||
														wp.i18n.__(
															'Retry',
															'media-cloud-sync'
														)}
													{loading.includes(
														`${log?.type}|${log?.media_id}|${log?.source_type}`
													) && (
														<img
															src={loaderBlue}
															alt={wp.i18n.__(
																'Loading',
																'media-cloud-sync'
															)}
														/>
													)}
												</button>
											</td>
										) : null}
									</tr>
								))
							) : (
								<tr>
									<td colSpan={retrySupported ? 3 : 2}>
										{labels.emptyText ||
											wp.i18n.__(
												'No errors found',
												'media-cloud-sync'
											)}
									</td>
								</tr>
							)}
						</tbody>
					)}
				</table>
			</div>
			<div className={style.pagination}>
				{page === 1 ? (
					<button
						type="button"
						className={ClassNames([style.button, style.active])}
						disabled
					>
						1
					</button>
				) : (
					<button
						type="button"
						className={ClassNames([style.button])}
						onClick={() => fetchLogs(1)}
					>
						1
					</button>
				)}

				{page - 2 > 1 ? (
					<button
						type="button"
						className={ClassNames([style.button])}
						disabled
					>
						...
					</button>
				) : null}

				{page - 1 > 1 ? (
					<button
						type="button"
						className={ClassNames([style.button])}
						onClick={() => fetchLogs(page - 1)}
					>
						{page - 1}
					</button>
				) : null}

				{page !== 1 && page !== totalPages ? (
					<button
						type="button"
						className={ClassNames([style.button, style.active])}
						disabled
					>
						{page}
					</button>
				) : null}

				{page + 1 < totalPages ? (
					<button
						type="button"
						className={ClassNames([style.button])}
						onClick={() => fetchLogs(page + 1)}
					>
						{page + 1}
					</button>
				) : null}

				{page + 2 < totalPages ? (
					<button
						type="button"
						className={ClassNames([style.button])}
						disabled
					>
						...
					</button>
				) : null}

				{page === totalPages && totalPages !== 1 ? (
					<button
						type="button"
						className={ClassNames([style.button, style.active])}
						disabled
					>
						{totalPages}
					</button>
				) : null}

				{page < totalPages && totalPages !== 1 ? (
					<button
						type="button"
						className={ClassNames([style.button])}
						onClick={() => fetchLogs(totalPages)}
					>
						{totalPages}
					</button>
				) : null}
			</div>
		</div>
	);
}
