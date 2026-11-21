import React, { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { toast } from 'react-toastify';
import Footer from '../Configure/Footer';
import style from './style.scss';
import {
	ClassNames,
	fetchWP,
	getDomainField,
	deliveryNeedsDomain
} from '../../helper';
import getSchema from '../../schema';
import TextInput from '../../components/TextInput';
import { setCdn } from '../../redux/slices/credential';
import { setSettingsData } from '../../redux/slices/common';
import WizardShell, { WizardIcon } from '../../components/WizardShell';

export default function ChooseCDN() {
	const dispatch = useDispatch();
	const navigate = useNavigate();
	const cdnServers = getSchema('cdnServers');
	const settingsDefault = getSchema('settingsDefaultValues');

	const location = useLocation();
	// Reached from the setup wizard's Save step (shows the step rail).
	const setupFlow = !!location?.state?.setup;
	const {
		cdn,
		service,
		serviceLabel,
		config: credentialConfig,
		bucketConfig
	} = useSelector((state) => state?.credentials);
	const { settings } = useSelector((state) => state?.common);
	const currentCdnServers = cdnServers?.[service];
	const hasCdnOptions =
		!!service &&
		!!currentCdnServers &&
		Object.keys(currentCdnServers).length > 0;

	const [saveLoader, setSaveLoader] = useState(false);
	const [activeCdn, setActiveCdn] = useState(() => {
		if (cdn?.service && currentCdnServers?.[cdn.service]) {
			return cdn.service;
		}

		return currentCdnServers ? Object.keys(currentCdnServers)[0] || '' : '';
	});
	const [errors, setErrors] = useState({});
	// Only the saved delivery's own domain is pre-filled; switching entries starts empty.
	const savedDomainFor = (entryKey) =>
		entryKey === cdn?.service && settings?.enable_cdn
			? settings?.cdn_url || ''
			: '';
	const [domain, setDomain] = useState(() => savedDomainFor(activeCdn));
	const activeEntry = currentCdnServers?.[activeCdn];
	const domainField = getDomainField(activeEntry);
	const domainChild = domainField?.childrens?.find(
		(child) => child?.name === 'cdn_url'
	);
	const [providerLabel, setProviderLabel] = useState(
		cdnServers?.[service]?.[cdn?.service]?.mainLabel?.length
			? ''
			: cdn?.label
	);

	const handleBack = async () => {
		navigate(setupFlow ? '/' : '/settings');
	};

	const validate = (nextSettings) => {
		const valid = {};
		// Entries with a CNAME field can't serve media without their domain.
		if (deliveryNeedsDomain(activeEntry, nextSettings)) {
			valid.domain = wp.i18n.__(
				'Enter the domain to deliver media from this provider.',
				'media-cloud-sync'
			);
		}
		if (cdnServers?.[service]?.[activeCdn]?.mainLabel?.length === 0) {
			if (providerLabel?.length === 0) {
				valid.providerLabel = wp.i18n.__(
					'Please enter a provider name',
					'media-cloud-sync'
				);
			}
		}

		setErrors(valid);
		return Object.keys(valid).length === 0;
	};

	const handleNext = async () => {
		setSaveLoader(true);

		let newSettings = { ...settings };
		// Keys with no default here are cleared server-side (saves merge, so omitting a key keeps it).
		const resetKeys = [];
		const resetKey = (key) => {
			if (key in (settingsDefault?.[service] || {})) {
				newSettings = { ...newSettings, [key]: settingsDefault[service][key] };
			} else {
				delete newSettings[key];
				resetKeys.push(key);
			}
		};

		if (!(cdn?.service === activeCdn && providerLabel === cdn?.label)) {
			getSchema('deliveryResetKeys').forEach(resetKey);
			currentCdnServers?.[cdn?.service]?.fields?.forEach((field) => {
				resetKey(field.name);
				field?.childrens?.forEach((child) => resetKey(child.name));
			});
		}

		if (domainField) {
			const trimmed = domain.trim();
			newSettings = {
				...newSettings,
				[domainField.name]: !!trimmed,
				cdn_url: trimmed
			};
		}

		if (!validate(newSettings)) {
			setSaveLoader(false);
			toast.error(
				wp.i18n.__(
					'Please fix the highlighted errors before proceeding',
					'media-cloud-sync'
				)
			);
			return;
		}

		const updateConfig = fetchWP
			.post('saveConfig', {
				action: 'cdn',
				cdn: {
					service: activeCdn,
					label: cdnServers?.[service]?.[activeCdn]?.mainLabel?.length
						? ''
						: providerLabel
				},
				settings: newSettings,
				reset_keys: resetKeys
			})
			.then((res) => {
				if (res.success) {
					dispatch(setSettingsData(newSettings));
					dispatch(
						setCdn({
							service: activeCdn,
							label: cdnServers?.[service]?.[activeCdn]?.mainLabel
								?.length
								? ''
								: providerLabel
						})
					);
					navigate(setupFlow ? '/' : '/settings');
					toast.success(res.message, {
						autoClose: 3000
					});
				} else {
					toast.error(res.message, {
						autoClose: 3000
					});
				}
				setSaveLoader(false);
			})
			.catch(() => {
				toast.error(
					wp.i18n.__(
						'Failed to save provider. Please try again.',
						'media-cloud-sync'
					),
					{
						autoClose: 3000
					}
				);
				setSaveLoader(false);
			});
		toast.promise(updateConfig, {
			pending: wp.i18n.__('Saving Provider', 'media-cloud-sync')
		});
	};

	const steps = setupFlow ? getSchema('configure_progress_bar') : [];
	const step = getSchema('configure_progress_bar')?.find(
		(item) => item?.slug === 'choose_cdn'
	);
	const storage = getSchema('services')?.[service];
	const railValues = {
		choose_provider: storage ? (
			<>
				{storage?.icon ? <img src={storage.icon} alt="" /> : null}
				{serviceLabel || storage?.label}
			</>
		) : null,
		configure_credentials:
			[bucketConfig?.bucket_name, credentialConfig?.region]
				.filter(Boolean)
				.join(' · ') || null,
		save_settings: getSchema('wizard')?.saved
	};
	const exit = setupFlow
		? { to: '/', label: wp.i18n.__('Exit to Dashboard', 'media-cloud-sync') }
		: { to: '/settings', label: wp.i18n.__('Back to Settings', 'media-cloud-sync') };

	if (!hasCdnOptions) {
		return (
			<WizardShell
				exit={exit}
				title={step?.title}
				desc={wp.i18n.__(
					'Configure storage before choosing a delivery provider.',
					'media-cloud-sync'
				)}
			>
				<Link to="/configure">
					{wp.i18n.__('Configure Storage', 'media-cloud-sync')}
				</Link>
			</WizardShell>
		);
	}

	return (
		<WizardShell
			steps={steps}
			current="choose_cdn"
			values={railValues}
			exit={exit}
			title={step?.title}
			desc={step?.desc}
			footer={
				<Footer
					labels={
						setupFlow
							? {
									back: wp.i18n.__('Skip for now', 'media-cloud-sync'),
									next: wp.i18n.__('Finish setup', 'media-cloud-sync'),
									icon: 'check'
							  }
							: {
									back: wp.i18n.__('Cancel', 'media-cloud-sync'),
									next: wp.i18n.__('Save', 'media-cloud-sync'),
									icon: 'save'
							  }
					}
					onBack={handleBack}
					onNext={handleNext}
					loading={saveLoader}
					disabled={{
						back: saveLoader,
						next: saveLoader
					}}
				/>
			}
		>
			<div className={style.cdnGrid} role="radiogroup">
				{Object.keys(currentCdnServers).map((cdnServer) => {
					const entry = currentCdnServers?.[cdnServer];
					const active = cdnServer === activeCdn;
					const select = () => {
						if (active) return;
						setActiveCdn(cdnServer);
						setDomain(savedDomainFor(cdnServer));
						setErrors((prev) => ({ ...prev, domain: '' }));
					};
					return (
						<div
							className={ClassNames([style.cdnTile, active ? style.active : null])}
							key={cdnServer}
							onClick={select}
							onKeyDown={(event) => {
								if (event.key === 'Enter' || event.key === ' ') {
									event.preventDefault();
									select();
								}
							}}
							role="radio"
							aria-checked={active}
							tabIndex={0}
						>
							{active ? (
								<span className={style.cdnTick}>
									<WizardIcon name="check" size={12} strokeWidth={3} />
								</span>
							) : null}
							<span className={style.cdnLogo}>
								<img src={entry?.icon} alt="" />
							</span>
							<strong>{entry?.label}</strong>
							<span className={style.cdnPills}>
								{entry?.speed ? (
									<span
										className={ClassNames([
											style.cdnPill,
											entry?.speed === 'Fast' ? style.fast : ''
										])}
									>
										<WizardIcon name="zap" size={12} strokeWidth={2} />
										{entry.speed}
									</span>
								) : null}
								{entry?.privateSupport ? (
									<span className={ClassNames([style.cdnPill, style.private])}>
										<WizardIcon name="lock" size={12} strokeWidth={2} />
										{wp.i18n.__('Private media', 'media-cloud-sync')}
									</span>
								) : null}
							</span>
						</div>
					);
				})}
			</div>
			{cdnServers?.[service]?.[activeCdn]?.mainLabel?.length <= 0 && (
				<div className={style.cdnField}>
					<TextInput
						type="text"
						label={wp.i18n.__('Provider Name', 'media-cloud-sync')}
						name="cdnLabel"
						icon="pencil"
						placeholder={wp.i18n.__('Enter Provider Name', 'media-cloud-sync')}
						value={providerLabel}
						error={errors?.providerLabel?.length > 0}
						errorMessage={errors?.providerLabel}
						onChange={(name, value) => {
							setErrors({
								providerLabel: value.length
									? ''
									: wp.i18n.__(
											'Please enter a provider name',
											'media-cloud-sync'
									  )
							});
							setProviderLabel(value);
						}}
					/>
				</div>
			)}
			{domainField ? (
				<div className={style.cdnField}>
					<TextInput
						type="text"
						label={domainField?.label}
						tooltip={domainField?.desc}
						name="cdn_url"
						icon="globe"
						placeholder={domainChild?.placeholder}
						value={domain}
						error={errors?.domain?.length > 0}
						errorMessage={errors?.domain}
						onChange={(name, value) => {
							setErrors((prev) => ({ ...prev, domain: '' }));
							setDomain(value);
						}}
					/>
				</div>
			) : null}
		</WizardShell>
	);
}
