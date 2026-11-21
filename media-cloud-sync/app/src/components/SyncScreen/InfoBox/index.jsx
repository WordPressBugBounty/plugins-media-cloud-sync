import React from 'react';
import { useSelector } from 'react-redux';

import style from './style.scss';
import { ClassNames } from '../../../helper';

export default function InfoBox({ progress, activeMode = 'cron', doneMessage = '' }) {
	const { settings } = useSelector((state) => state?.common);
	const { service } = useSelector((state) => state?.credentials);

	if (!(settings?.copy_to_bucket && service)) {
		return (
			<div className={style.infoBox}>
				{!(settings?.copy_to_bucket && service) && (
					<div className={ClassNames([style.message, style.error])}>
						{wp.i18n.__(
							'Your plugin settings are not configured properly. Please ensure Service is enabled and Copy files to bucket is enabled.',
							'media-cloud-sync'
						)}
					</div>
				)}
			</div>
		);
	}

	if (
		settings?.copy_to_bucket &&
		service &&
		progress?.status === 'stopped' &&
		(progress?.total || 0) === 0 &&
		!progress?.idle_message
	) {
		return (
			<div className={style.infoBox}>
				<div className={ClassNames([style.message, style.success])}>
					{doneMessage ||
						wp.i18n.__(
							'Nothing to process right now.',
							'media-cloud-sync'
						)}
				</div>
			</div>
		);
	}

	if (
		settings?.copy_to_bucket &&
		service &&
		progress?.status === 'completed'
	) {
		return (
			<div className={style.infoBox}>
				<div className={ClassNames([style.message, style.success])}>
					{doneMessage ||
						wp.i18n.__(
							'Finished. See the results below.',
							'media-cloud-sync'
						)}
				</div>
			</div>
		);
	}

	if (settings?.copy_to_bucket && service && progress?.status === 'paused') {
		return (
			<div className={style.infoBox}>
				<div className={ClassNames([style.message, style.warning])}>
					{wp.i18n.__(
						'Sync is paused. You can resume it by clicking the "Resume" button or stop it by clicking the "Stop" button.',
						'media-cloud-sync'
					)}
				</div>
			</div>
		);
	}

	// Pause/stop takes effect between items, not mid-item — show that it's pending.
	if (
		settings?.copy_to_bucket &&
		service &&
		progress?.status === 'running' &&
		(progress?.pause_requested || progress?.stop_requested)
	) {
		const requestMessage = progress?.stop_requested
			? wp.i18n.__(
					'Stopping your sync — it will stop as soon as the item currently in progress finishes.',
					'media-cloud-sync'
				)
			: wp.i18n.__(
					'Pausing your sync — it will pause as soon as the item currently in progress finishes.',
					'media-cloud-sync'
				);

		return (
			<div className={style.infoBox}>
				<div className={ClassNames([style.message, style.warning])}>
					{requestMessage}
				</div>
			</div>
		);
	}

	if (settings?.copy_to_bucket && service && progress?.status === 'running') {
		// "you can safely close this window" is only true for Cron (and for Mixed's
		// fallback) — it would directly contradict Ajax mode's own messaging, which
		// requires staying in the plugin admin for the sync to keep progressing.
		const runningMessage =
			activeMode === 'ajax'
				? wp.i18n.__(
						"Your sync is running live in this browser. Stay in the Media Cloud Sync admin — closing this tab or navigating away will pause it, and it picks back up automatically when you return.",
						'media-cloud-sync'
					)
				: wp.i18n.__(
						'Your sync is running in the background. It may take some time, and regular site visits help it complete. Your site will stay online, and you can safely close this window.',
						'media-cloud-sync'
					);

		return (
			<div className={style.infoBox}>
				<div className={ClassNames([style.message, style.info])}>
					{runningMessage}
				</div>
			</div>
		);
	}
}
