import React, { useEffect } from 'react';
import { toast } from 'react-toastify';

import style from './style.scss';
import { PrimaryButton } from '../Buttons';
import { fetchWP } from '../../helper';

import loadingImage from '../../images/loader-blue.svg';

export default function UpgradeNotice() {
	const [startLoading, setStartLoading] = React.useState(false);
	const [pageLoading, setPageLoading] = React.useState(true);
	const [upgrading, setUpgrading] = React.useState(false);
	const [progress, setProgress] = React.useState(0);
	const pollTimeoutRef = React.useRef(null);
	const isMountedRef = React.useRef(true);

	// Fetch upgrade status
	const fetchUpgradeStatus = async () => {
		await fetchWP.get('upgrade/status').then((response) => {
			if (!isMountedRef.current) return;
			const { status, percentage } = response;
			if (status !== 'not_started') {
				setUpgrading(true);
				setProgress(percentage);
				if (status === 'completed') {
					setProgress(100);
					setPageLoading(true);
					toast.success(
						wp.i18n.__(
							'Database upgrade completed successfully.',
							'media-cloud-sync'
						)
					);
					setTimeout(() => {
						window.location.reload();
					}, 1000);
				} else {
					pollTimeoutRef.current = setTimeout(
						fetchUpgradeStatus,
						2000
					);
				}
			}
		});
	};

	const startUpgrade = async () => {
		setStartLoading(true);
		await fetchWP.post('upgrade/start').then((response) => {
			if (response?.status === 'running') {
				setUpgrading(true);
				setProgress(response?.percentage || 0);
				toast.success(
					wp.i18n.__(
						'Database upgrade started successfully.',
						'media-cloud-sync'
					)
				);
			} else {
				setUpgrading(false);
				setProgress(0);
				toast.error(
					wp.i18n.__(
						'Failed to start database upgrade.',
						'media-cloud-sync'
					)
				);
			}
			setStartLoading(false);
		});
	};

	const getInitialStatus = async () => {
		await fetchUpgradeStatus();
		setPageLoading(false);
	};

	useEffect(() => {
		isMountedRef.current = true;
		getInitialStatus();
		return () => {
			isMountedRef.current = false;
			clearTimeout(pollTimeoutRef.current);
		};
	}, []);

	return (
		<div className={style.upgradeNotice}>
			<h2>
				{wp.i18n.__('Database Upgrade Required', 'media-cloud-sync')}
			</h2>
			{pageLoading ? (
				<img src={loadingImage} alt="" />
			) : (
				<>
					{upgrading ? (
						<>
							<p>
								{wp.i18n.__(
									'Database upgrade in progress. Please wait for the process to complete. Do not close or refresh this page during the upgrade.',
									'media-cloud-sync'
								)}
							</p>

							<div className={style.progressBarContainer}>
								<div className={style.progressBar}>
									<div
										className={style.progressFill}
										style={{ width: `${progress}%` }}
									></div>
								</div>
								<div className={style.progressPercent}>
									{progress}%
								</div>
							</div>
						</>
					) : (
						<>
							<p>
								{wp.i18n.__(
									'To ensure compatibility with the latest version of Media Cloud Sync, a database upgrade is necessary.',
									'media-cloud-sync'
								)}
								<br />
								{wp.i18n.__(
									'It is recommended to back up your database before proceeding with the upgrade.',
									'media-cloud-sync'
								)}{' '}
								<br />
								<br />
								{wp.i18n.__(
									'Please click the button below to initiate the upgrade process.',
									'media-cloud-sync'
								)}
							</p>
							<PrimaryButton
								label={wp.i18n.__(
									'Upgrade Database',
									'media-cloud-sync'
								)}
								type="button"
								className={style.upgradeButton}
								onClick={startUpgrade}
								disabled={startLoading}
								loading={startLoading}
							/>
						</>
					)}
				</>
			)}
		</div>
	);
}
