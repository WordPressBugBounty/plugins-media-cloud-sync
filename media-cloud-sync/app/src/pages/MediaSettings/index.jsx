import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { toast } from 'react-toastify';

import style from './style.scss';
import getSchema from '../../schema';
import { setSettingsData, setStatus } from '../../redux/slices/common';
import {
	ClassNames,
	fetchWP,
	validateFormField
} from '../../helper';
import ToggleButton from '../../components/ToggleButton';
import SelectInput from '../../components/SelectInput';
import TextInput from '../../components/TextInput';
import TextareaInput from '../../components/TextareaInput';
import TagsInput from '../../components/TagsInput';
import ConfirmDialog from '../../components/ConfirmDialog';
import { PrimaryButton, SecondaryButton } from '../../components/Buttons';
import ConnectionStatusCheck from '../../components/ConnectionStatusCheck';
import FieldActionPanel from '../../components/FieldActionPanel';
import ProLock from '../../components/ProLock';
import useConnectionStatusCheck from '../../hooks/useConnectionStatusCheck';

const getField = ({
	field,
	config,
	handleChange,
	onWarningNeeded,
	disabled = false,
	isChild = false
}) => {
	const { showErrors, errors, settings: currentSettings } = config;
	const error = errors?.find((err) => err.name === field?.name) || null;
	switch (field.type) {
		case 'toggle':
			return (
				<ToggleButton
					name={field?.name}
					key={field?.name}
					value={currentSettings?.[field?.name]}
					onChange={(name, value) => {
						if (value && field?.warning?.enable && onWarningNeeded) {
							onWarningNeeded(field);
						} else {
							handleChange(name, value);
						}
					}}
					size="small"
					disabled={disabled}
				/>
			);
		case 'select':
			return (
				<SelectInput
					type="text"
					name={field?.name}
					key={field?.name}
					options={field?.options}
					placeholder={field?.placeholder}
					value={currentSettings?.[field?.name]}
					error={showErrors && error}
					errorMessage={error?.message}
					onChange={handleChange}
				/>
			);
		case 'number':
			return (
				<TextInput
					type="number"
					name={field?.name}
					key={field?.name}
					label={field?.label}
					tooltip={field?.desc}
					compact={isChild}
					placeholder={field?.placeholder}
					value={currentSettings?.[field?.name]}
					error={showErrors && error}
					errorMessage={error?.message}
					min={field?.min}
					max={field?.max}
					step={field?.step}
					onChange={(name, value) =>
						handleChange(
							name,
							value === '' ? '' : Math.max(field?.min ?? 1, Math.trunc(Number(value)))
						)
					}
				/>
			);
		case 'textarea':
			return (
				<TextareaInput
					name={field?.name}
					key={field?.name}
					label={field?.label}
					tooltip={field?.desc}
					placeholder={field?.placeholder}
					value={currentSettings?.[field?.name]}
					error={showErrors && error}
					errorMessage={error?.message}
					onChange={handleChange}
				/>
			);
		case 'tags':
			return (
				<TagsInput
					name={field?.name}
					key={field?.name}
					placeholder={field?.placeholder}
					value={currentSettings?.[field?.name] || []}
					error={showErrors && error}
					errorMessage={error?.message}
					onChange={handleChange}
				/>
			);
		default:
			return (
				<TextInput
					type={field?.inputType || 'text'}
					name={field?.name}
					key={field?.name}
					label={field?.label}
					tooltip={field?.desc}
					compact={isChild}
					placeholder={field?.placeholder}
					value={currentSettings?.[field?.name]}
					error={showErrors && error}
					errorMessage={error?.message}
					onChange={handleChange}
				/>
			);
	}
};

// Child width comes from the schema: `width: 'half'` pairs up, anything else takes the full row.
const isWideChild = (child) => child?.width !== 'half';

// One setting: label, description and switch; when on, its child fields, notice and extra panel.
const SettingRow = ({ field, config, handleChange, onWarningNeeded, disabled, extra = null }) => {
	const { settings: currentSettings } = config;
	const isOn = !!currentSettings?.[field?.name] && !field?.preview;
	const children = (field?.childrens || []).filter((child) => !child?.hidden);
	const childCount = children.length;

	return (
		<div className={style.row}>
			<div className={style.rowHead}>
				<div className={ClassNames([style.rowText, field?.preview ? style.faded : null])}>
					<label htmlFor={field?.name}>{field?.label}</label>
					{field?.desc || field?.documentationLink ? (
						<p>
							{field?.desc}{' '}
							{field?.documentationLink ? (
								<a href={field.documentationLink} target="_blank" rel="noreferrer">
									{field?.documentationLabel}
								</a>
							) : null}
						</p>
					) : null}
				</div>
				<div className={style.control}>
					{field?.preview ? (
						<ProLock />
					) : (
						getField({ field, config, handleChange, onWarningNeeded, disabled })
					)}
				</div>
			</div>
			{isOn && childCount ? (
				<div className={style.children}>
					{children.map((child) => (
						<div
							key={child?.name}
							className={isWideChild(child) ? style.wide : null}
						>
							{getField({ field: child, config, handleChange, isChild: true })}
						</div>
					))}
				</div>
			) : null}
			{isOn && field?.notice ? <p className={style.notice}>{field.notice}</p> : null}
			{isOn ? extra : null}
		</div>
	);
};

// Groups fields by `section`, keeping the order sections first appear in.
const groupBySection = (fields = []) =>
	fields.reduce((groups, field) => {
		const key = field?.section || '';
		const group = groups.find((item) => item.key === key);
		if (group) {
			group.fields.push(field);
		} else {
			groups.push({ key, fields: [field] });
		}
		return groups;
	}, []);

const getFieldValidation = (field) => {
	if (field?.validation) {
		return field.validation;
	}

	if (field?.required) {
		return { required: true };
	}

	return {};
};

export default function MediaSettings() {
	const dispatch = useDispatch();
	const services = getSchema('services');
	const cdnServers = getSchema('cdnServers');
	const settingsDefault = getSchema('settingsDefaultValues');

	const {
		service,
		serviceLabel,
		cdn,
		bucketConfig,
		configSource,
		serviceEnabled
	} = useSelector((state) => state?.credentials);
	const { settings, status } = useSelector((state) => state?.common);
	const allSyncStatistics = useSelector((state) => state?.sync);

	const [loading, setLoading] = useState({ disabled: false, loading: false });
	const [hasSyncRunning, setHasSyncRunning] = React.useState(false);
	const { loading: statusLoading, runCheck: runConnectionStatusCheck } =
		useConnectionStatusCheck();
	const [showSave, setShowSave] = useState(false);
	const [config, setConfig] = useState({
		settings: {},
		showErrors: false,
		errors: []
	});
	const [pendingWarningField, setPendingWarningField] = useState(null);

	const currentService = services?.[service];
	const currentCdnServer = cdnServers?.[service]?.[cdn?.service];
	const storageOperational =
		!!service && serviceEnabled !== false;

	useEffect(() => {
		dispatch(
			setSettingsData({
				...settingsDefault?.[service],
				...settings
			})
		);
	}, []);

	useEffect(() => {
		setConfig({
			...config,
			settings: {
				...config?.settings,
				...settings
			}
		});
	}, [settings]);

	React.useEffect(() => {
		// Check if any other sync is running
		const isOtherRunning = Object.values(allSyncStatistics).some(
			(sync) => sync?.status === 'running' || sync?.status === 'paused'
		);
		setHasSyncRunning(isOtherRunning);
	}, [allSyncStatistics]);

	const validate = () => {
		const { settings: currentSettings } = config;

		const errors = [];
		currentService?.settings?.forEach((field) => {
			if (field?.hidden) return;
			const valid = validateFormField(
				getFieldValidation(field),
				currentSettings?.[field?.name]
			);

			if (!valid?.valid) {
				errors.push({
					name: field?.name,
					message: valid?.message || ''
				});
			}

			if (currentSettings?.[field?.name] && field?.childrens?.length) {
				field?.childrens?.forEach((child) => {
					const childValid = validateFormField(
						getFieldValidation(child),
						currentSettings?.[child?.name]
					);

					if (!childValid?.valid) {
						errors.push({
							name: child?.name,
							message: childValid?.message || ''
						});
					}
				});
			}
		});

		currentCdnServer?.fields?.forEach((field) => {
			if (field?.hidden) return;
			const valid = validateFormField(
				getFieldValidation(field),
				currentSettings?.[field?.name]
			);

			if (!valid?.valid) {
				errors.push({
					name: field?.name,
					message: valid?.message || ''
				});
			}

			if (currentSettings?.[field?.name] && field?.childrens?.length) {
				field?.childrens?.forEach((child) => {
					const childValid = validateFormField(
						getFieldValidation(child),
						currentSettings?.[child?.name]
					);

					if (!childValid?.valid) {
						errors.push({
							name: child?.name,
							message: childValid?.message || ''
						});
					}
				});
			}
		});

		setConfig({
			...config,
			showErrors: !!errors?.length,
			errors
		});

		return !errors?.length;
	};

	const handleSaveSettings = () => {
		setLoading({ disabled: true, loading: true });
		const valid = validate();

		if (!valid) {
			setLoading({ disabled: false, loading: false });
			toast.error(
				wp.i18n.__(
					'Please fix the highlighted errors before proceeding',
					'media-cloud-sync'
				),
				{
					autoClose: 3000
				}
			);
			return;
		}

		const updateConfig = fetchWP
			.post('saveConfig', {
				action: 'settings',
				settings: config.settings
			})
			.then((res) => {
				if (res.success) {
					dispatch(setSettingsData(config.settings));
					Object.entries(res?.status || {}).forEach(([key, value]) =>
						dispatch(setStatus({ key, value }))
					);
					toast.success(res.message, {
						autoClose: 3000
					});
				} else {
					toast.error(res.message, {
						autoClose: 3000
					});
				}
				setLoading({ disabled: false, loading: false });
			})
			.catch(() => {
				toast.error(
					wp.i18n.__(
						'Failed to save settings. Please try again.',
						'media-cloud-sync'
					),
					{
						autoClose: 3000
					}
				);
				setLoading({ disabled: false, loading: false });
			});
		toast.promise(updateConfig, {
			pending: wp.i18n.__('Saving configuration', 'media-cloud-sync')
		});
	};

	const handleChange = (field, key, value) => {
		// Validate Individual Field
		if (config?.showErrors) {
			let valid = { valid: true, message: '' };
			if (field?.name === key) {
				valid = validateFormField(getFieldValidation(field), value);
			} else if (field?.childrens?.length) {
				field?.childrens?.forEach((child) => {
					if (child?.name === key) {
						valid = validateFormField(getFieldValidation(child), value);
					}
				});
			}

			const errors = config?.errors?.filter((err) => err?.name !== key);
			const updatedErrors = valid?.valid
				? errors
				: [...errors, { name: key, message: valid?.message }];

			setConfig({
				...config,
				errors: updatedErrors,
				settings: {
					...config?.settings,
					[key]: value
				}
			});

			setLoading({ disabled: !!updatedErrors?.length, loading: false });
		} else {
			setConfig({
				...config,

				settings: {
					...config?.settings,
					[key]: value
				}
			});
		}
	};

	useEffect(() => {
		if (!service) {
			return;
		}

		const shouldCheckStorage =
			configSource === 'config' || serviceEnabled === false;

		// When storage needs a check, run both; otherwise refresh delivery only.
		// Do not skip CDN when storage has errors (serviceEnabled === false).
		if (shouldCheckStorage) {
			runConnectionStatusCheck('all');
		} else {
			runConnectionStatusCheck('cdn');
		}
	}, [service, configSource, serviceEnabled, runConnectionStatusCheck]);

	useEffect(() => {
		setShowSave(
			JSON.stringify(config?.settings) !== JSON.stringify(settings)
		);
	}, [config?.settings]);

	const page = getSchema('settingsPage') || {};
	const sectionTitles = getSchema('settingsSections') || {};
	const visible = (fields) =>
		(fields || []).filter(
			(field) => !field?.hidden
		);

	const renderGroups = (fields, rowProps) =>
		groupBySection(visible(fields)).map((group) => (
			<div className={style.group} key={group.key || 'general'}>
				{group.key && sectionTitles?.[group.key] ? (
					<div className={style.band}>{sectionTitles[group.key]}</div>
				) : null}
				{group.fields.map((field) => (
					<SettingRow
						key={field?.name}
						field={field}
						config={config}
						onWarningNeeded={setPendingWarningField}
						handleChange={(name, value) => handleChange(field, name, value)}
						{...rowProps(field)}
					/>
				))}
			</div>
		));

	const externalArrow = (
		<svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
			<path
				d="M9 5h10v10M19 5 6 18"
				stroke="currentColor"
				strokeWidth="2.2"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);

	return (
		<>
			<div className={style.settingsPage}>
				<div className={style.pageHead}>
					<h2>{page.title || wp.i18n.__('Settings', 'media-cloud-sync')}</h2>
					{page.description ? <p>{page.description}</p> : null}
				</div>

				<div className={style.columns}>
					<section className={style.card}>
						<div className={ClassNames([style.banner, style.storageBanner])}>
							<div className={style.provider}>
								<span className={style.logo}>
									<img src={currentService?.icon} alt="" />
								</span>
								<div className={style.providerText}>
									<span className={style.eyebrow}>{page.storage}</span>
									<div className={style.providerLine}>
										<strong>
											{currentService?.mainLabel?.length
												? currentService?.mainLabel
												: serviceLabel}
										</strong>
										{bucketConfig?.bucket_name ? (
											<>
												<span className={style.sep} aria-hidden="true" />
												<a
													href={
														currentService?.consoleLink?.replace(
															'<bucket_name>',
															bucketConfig?.bucket_name
														) || '#'
													}
													target="_blank"
													rel="noreferrer"
												>
													{bucketConfig?.bucket_name}
													{externalArrow}
												</a>
											</>
										) : null}
									</div>
								</div>
								<PrimaryButton
									variant="corner"
									label={page.change}
									type="link"
									link="/configure"
									className={style.change}
								/>
							</div>
							<ConnectionStatusCheck
								check="storage"
								status={status}
								loading={statusLoading}
								onRefresh={() => runConnectionStatusCheck('storage')}
							/>
						</div>
						{renderGroups(currentService?.settings, (field) => ({
							disabled: !storageOperational || !!field?.preview
						}))}
					</section>

					<section className={style.card}>
						<div className={ClassNames([style.banner, style.deliveryBanner])}>
							<div className={style.provider}>
								{currentCdnServer ? (
									<span className={style.logo}>
										<img src={currentCdnServer?.icon} alt="" />
									</span>
								) : null}
								<div className={style.providerText}>
									<span className={style.eyebrow}>{page.delivery}</span>
									{currentCdnServer ? (
										<div className={style.providerLine}>
											<strong>
												{currentCdnServer?.mainLabel
													? currentCdnServer?.mainLabel
													: `${cdn?.label}`}
											</strong>
											{currentCdnServer?.consoleLink ? (
												<>
													<span className={style.sep} aria-hidden="true" />
													<a
														href={currentCdnServer.consoleLink}
														target="_blank"
														rel="noreferrer"
													>
														{currentCdnServer?.consoleLabel}
														{externalArrow}
													</a>
												</>
											) : null}
										</div>
									) : (
										<>
											<div className={style.providerLine}>
												<strong>{page.noDelivery}</strong>
											</div>
											<p className={style.providerNote}>{page.noDeliveryText}</p>
										</>
									)}
								</div>
								<PrimaryButton
									variant="corner"
									label={page.change}
									type="link"
									link="/choose_cdn"
									className={style.change}
								/>
							</div>
							{currentCdnServer ? (
								<ConnectionStatusCheck
									check="cdn"
									status={status}
									loading={statusLoading}
									onRefresh={() => runConnectionStatusCheck('cdn')}
								/>
							) : null}
						</div>
						{renderGroups(currentCdnServer?.fields, (field) => ({
							disabled: !!field?.preview,
							extra: field?.action ? (
								<FieldActionPanel
									action={field.action}
									status={status}
									service={service}
									savedSettings={settings}
									draftSettings={config?.settings}
								/>
							) : null
						}))}
					</section>
				</div>
			</div>

			<div
				className={ClassNames([style.saveBar, showSave ? style.active : null])}
				aria-hidden={!showSave}
			>
				<span className={style.saveDot} aria-hidden="true" />
				<span className={style.saveText}>
					{hasSyncRunning ? page.syncRunning : page.unsaved}
				</span>
				<SecondaryButton
					label={page.cancel || wp.i18n.__('Cancel', 'media-cloud-sync')}
					type="button"
					onClick={() => {
						setConfig({
							...config,
							showErrors: false,
							errors: [],
							settings: {
								...settings
							}
						});
					}}
				/>
				<PrimaryButton
					label={page.save || wp.i18n.__('Save Changes', 'media-cloud-sync')}
					loading={loading?.loading}
					disabled={
						loading?.disabled ||
						loading?.loading ||
						hasSyncRunning ||
						!storageOperational
					}
					type="button"
					onClick={handleSaveSettings}
				/>
			</div>

			<ConfirmDialog
				open={!!pendingWarningField}
				danger
				title={pendingWarningField?.warning?.title || pendingWarningField?.label}
				messageHtml={pendingWarningField?.warning?.message}
				confirmLabel={
					pendingWarningField?.warning?.confirmLabel ||
					wp.i18n.__('Enable', 'media-cloud-sync')
				}
				onCancel={() => setPendingWarningField(null)}
				onConfirm={() => {
					handleChange(
						pendingWarningField,
						pendingWarningField?.name,
						true
					);
					setPendingWarningField(null);
				}}
			/>
		</>
	);
}
