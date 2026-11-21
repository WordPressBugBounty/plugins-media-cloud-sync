import React, { useEffect, useState } from 'react';
import { useSelector } from 'react-redux';
import { toast } from 'react-toastify';

import style from './style.scss';
import getSchema from '../../schema';
import { ClassNames, fetchWP } from '../../helper';

import WizardIcon from '../../components/WizardShell/icons';

const PERMISSION_ICONS = {
	checkExist: 'search',
	addNewBucket: 'databasePlus',
	write: 'upload',
	delete: 'trash'
};

export default function VerifyPermissions({ setConfig, handleNext }) {
	const services = getSchema('services');

	const {
		service,
		config: tempConfig,
		configSource,
		bucketData
	} = useSelector((state) => state?.tempCredentials);
	const { addNew } = bucketData;

	const currentService = services?.[service];

	const [runVerify, setRunVerify] = useState(false);
	const [verifyComplete, setVerifyComplete] = useState(false);

	const [permissions, setPermissions] = useState({
		[addNew ? 'addNewBucket' : 'checkExist']: {
			message: addNew
				? `${wp.i18n.__('Adding new', 'media-cloud-sync')} ${
						currentService?.storage?.label
				  }`
				: `${wp.i18n.__('Checking', 'media-cloud-sync')} ${
						currentService?.storage?.label
				  } ${wp.i18n.__('exists', 'media-cloud-sync')}`,
			endpoint: addNew ? 'bucket/addNew' : 'bucket/checkExist',
			checked: false,
			loading: false,
			retry: false,
			skiped: false,
			canSkip: false,
			error: false,
			errorMessage: ''
		},
		write: {
			message: `${wp.i18n.__(
				'Checking write permissions',
				'media-cloud-sync'
			)}`,
			endpoint: 'write',
			checked: false,
			loading: false,
			retry: false,
			skiped: false,
			canSkip: false,
			error: false,
			errorMessage: ''
		},
		delete: {
			message: `${wp.i18n.__(
				'Checking delete permissions',
				'media-cloud-sync'
			)}`,
			endpoint: 'delete',
			checked: false,
			loading: false,
			retry: false,
			skiped: false,
			canSkip: true,
			error: false,
			errorMessage: ''
		}
	});

	const runEndpoint = async (key) => {
		const { endpoint } = permissions[key];

		try {
			const response = await fetchWP.post(
				endpoint.includes('/') ? endpoint : `permissions/${endpoint}`,
				{
					service,
					configSource,
					config: configSource === 'config' ? {} : tempConfig,
					bucketData
				}
			);

			if (!response.success) {
				toast.error(response.message, {
					autoClose: 4000
				});
			}

			return {
				error: !response.success,
				errorMessage: response.message
			};
		} catch (error) {
			const errorMessage =
				error?.message ||
				wp.i18n.__(
					'Permission check failed. Please try again.',
					'media-cloud-sync'
				);

			toast.error(errorMessage, {
				autoClose: 4000
			});

			return {
				error: true,
				errorMessage
			};
		}
	};

	const permissionKeys = Object.keys(permissions);

	async function runPermissions() {
		if (runVerify) {
			setConfig((prevState) => ({
				...prevState,
				disabled: {
					next: true,
					back: true
				}
			}));
			let currentPermissions = { ...permissions };

			for (let i = 0; i < permissionKeys.length; i += 1) {
				// eslint-disable-next-line no-loop-func
				const currentKey = permissionKeys?.find((permission) => {
					const currentPermission = {
						...currentPermissions?.[permission]
					};
					return (
						!currentPermission?.checked ||
						(currentPermission?.checked &&
							currentPermission?.error &&
							!currentPermission?.skiped &&
							currentPermission?.retry)
					);
				});

				if (currentKey) {
					currentPermissions = {
						...currentPermissions,
						[currentKey]: {
							...currentPermissions[currentKey],
							retry: false,
							loading: true
						}
					};
					setPermissions(currentPermissions);

					// eslint-disable-next-line no-await-in-loop
					const result = await runEndpoint(currentKey);

					if (result) {
						currentPermissions = {
							...currentPermissions,
							[currentKey]: {
								...currentPermissions[currentKey],
								checked: true,
								loading: false,
								error: result?.error,
								errorMessage: result?.errorMessage
							}
						};
						setPermissions(currentPermissions);
					}

					if (
						result.error &&
						!currentPermissions[currentKey].skiped
					) {
						setConfig((prevState) => ({
							...prevState,
							disabled: {
								next: true,
								back: false
							}
						}));
						break;
					}
				}
				if (i === permissionKeys.length - 1) {
					setVerifyComplete(true);
				}
			}
			setPermissions(currentPermissions);
			setRunVerify(false);
		}
	}

	const handleSkip = (key) => {
		setPermissions((prevState) => ({
			...prevState,
			[key]: {
				...prevState[key],
				skiped: true
			}
		}));
		setRunVerify(true);
	};

	const handleRetry = (key) => {
		setPermissions((prevState) => ({
			...prevState,
			[key]: {
				...prevState[key],
				retry: true,
				skiped: false,
				error: false,
				checked: false,
				errorMessage: ''
			}
		}));
		setRunVerify(true);
	};

	useEffect(() => {
		if (verifyComplete) {
			setConfig((prevState) => ({
				...prevState,
				disabled: {
					next: false,
					back: false
				}
			}));

			handleNext();
		}
	}, [verifyComplete]);
	useEffect(() => {
		setRunVerify(true);
	}, []);
	useEffect(() => {
		runPermissions();
	}, [runVerify]);

	const failedKey = Object.keys(permissions).find(
		(key) => permissions[key].error && !permissions[key].skiped
	);

	return (
		<div className={style.verifyPermissionPage}>
			{Object.keys(permissions).map((permission) => {
				const item = permissions[permission];
				const failed = item.checked && item.error && !item.skiped;
				const passed = item.checked && !item.error;
				let state = 'wait';
				let detail = wp.i18n.__('Waiting', 'media-cloud-sync');
				if (item.loading) {
					state = 'run';
					detail = '';
				} else if (item.skiped) {
					state = 'skipped';
					detail = item.errorMessage;
				} else if (failed) {
					state = 'bad';
					detail = item.errorMessage;
				} else if (passed) {
					state = 'ok';
					detail = wp.i18n.__('Passed', 'media-cloud-sync');
				}
				return (
					<div
						className={ClassNames([style.check, style[state]])}
						key={permission}
						aria-busy={item.loading}
					>
						<span className={style.checkIcon}>
							{item.loading ? (
								<span className={style.spinner} />
							) : (
								<WizardIcon
									name={PERMISSION_ICONS[permission] || 'shieldCheck'}
									size={18}
								/>
							)}
						</span>
						<div className={style.checkText}>
							<strong>{item.message}</strong>
							{detail && state !== 'ok' ? <span>{detail}</span> : null}
						</div>
						{failed ? (
							<div className={style.actions}>
								{item?.canSkip && (
									<button
										className={style.skip}
										type="button"
										onClick={() => handleSkip(permission)}
									>
										{wp.i18n.__('Skip', 'media-cloud-sync')}
									</button>
								)}
								<button
									className={style.retry}
									type="button"
									onClick={() => handleRetry(permission)}
								>
									<WizardIcon name="undo" size={14} strokeWidth={2} />
									{wp.i18n.__('Retry', 'media-cloud-sync')}
								</button>
							</div>
						) : null}
						{passed ? (
							<span className={ClassNames([style.badge, style.badgeOk])}>
								<WizardIcon name="check" size={12} strokeWidth={3} />
								{wp.i18n.__('Passed', 'media-cloud-sync')}
							</span>
						) : null}
						{item.loading ? (
							<span className={style.badge}>
								{wp.i18n.__('Checking', 'media-cloud-sync')}
							</span>
						) : null}
						{item.skiped ? (
							<span className={style.badge}>
								{wp.i18n.__('Skipped', 'media-cloud-sync')}
							</span>
						) : null}
					</div>
				);
			})}
			{failedKey && permissions[failedKey]?.canSkip ? (
				<div className={style.warning}>
					<WizardIcon name="warn" size={16} />
					{wp.i18n.__(
						'If you skip this, deleting media won’t remove its files from the cloud.',
						'media-cloud-sync'
					)}
				</div>
			) : null}
		</div>
	);
}
