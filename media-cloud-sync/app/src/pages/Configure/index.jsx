import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { toast } from 'react-toastify';
import { escapeHtml, fetchWP, validateFormField } from '../../helper';
import { setTemporaryData } from '../../redux/slices/tempCredential';
import { setCredentials } from '../../redux/slices/credential';
import getSchema from '../../schema';
import SetCredentials from './setCredentials';
import Footer from './Footer';
import Provider from './provider';
import SaveSettings from './saveSettings';
import Welcome from './welcome';
import style from './style.scss';
import SetBucket from './setBucket';
import { setSettingsData, setStatus } from '../../redux/slices/common';
import LoadingPopup from './LoadingPopup';
import VerifyPermissions from './verifyPermissions';
import SetSecurity from './setSecurity';
import WizardShell, { WizardIcon } from '../../components/WizardShell';

export default function Configure({ isConfigured }) {
	const dispatch = useDispatch();
	const navigate = useNavigate();

	const tempData = useSelector((state) => state?.tempCredentials);
	const {
		service: tempService,
		serviceLabel: tempServiceLabel,
		cdn: tempCdn,
		config: tempConfig,
		configSource: tempConfigSource,
		bucketData: tempBucketData,
		security: tempSecurity
	} = tempData;

	const { config: tempBucketConfig, addNewConfig: tempAddNewConfig } =
		tempBucketData;

	const savedCredentials = useSelector((state) => state?.credentials);
	const { settings: currentSettings } = useSelector(
		(state) => state?.common
	);

	const settingsDefault = getSchema('settingsDefaultValues');
	const progressBarItems = getSchema('configure_progress_bar');
	const footerConfig = getSchema('configure_footer');
	const bucketDefaultConfig = getSchema('bucketDefaultValues');
	const services = getSchema('services');
	const wizard = getSchema('wizard') || {};
	const [config, setConfig] = useState({
		verifyCredentials: false,
		verifyMessage: '',
		disabled: {
			next: false,
			back: false
		},
		showProviderValidateMessage: false,
		showCredentialsValidateMessage: false,
		showBucketValidateMessage: false,
		showListBucketValidateMessage: false,
		listBucketValidateMessage: ''
	});
	const [errorFields, setErrorFields] = useState([]);
	const [saveLoader, setSaveLoader] = useState(false);
	const configurePageSlugs = services?.[tempService]?.configurePageSlugs;
	const [currentConfigurePage, setCurrentConfigurePage] =
		useState('setCredentials');
	const [current, setCurrent] = useState(
		isConfigured ? 'choose_provider' : 'welcome'
	);

	const pageSlugs = [
		'welcome',
		'choose_provider',
		'configure_credentials',
		'save_settings'
	];

	/**
	 * Validates the data based on the currentConfigurePage.
	 * If currentConfigurePage is `setCredentials`, it validates the credentials fields.
	 * If currentConfigurePage is `setBucket`, it validates the bucket fields.
	 * @returns {boolean} If the data is valid, it returns true, otherwise false.
	 */
	const validateData = () => {
		const service = services?.[tempService];
		const errors = [];
		switch (current) {
			case 'choose_provider':
				if (!service?.mainLabel?.length && !tempServiceLabel?.length) {
					errors.push({
						name: 'serviceLabel',
						message: wp.i18n.__(
							'Please enter a service name',
							'media-cloud-sync'
						)
					});
				}
				break;
			case 'configure_credentials':
				switch (currentConfigurePage) {
					case 'setCredentials':
						// In 'config' source mode there are no input fields to validate;
						// the server verifies the WPMCS_CONFIG constant during verifyCredentials.
						if (tempConfigSource !== 'config') {
							service?.fields?.forEach(({ validation, name }) => {
								const valid = validateFormField(
									validation || {},
									tempConfig?.[name] || ''
								);
								if (!valid?.valid) {
									errors.push({
										name,
										message: valid?.message || ''
									});
								}
							});
						}
						break;
					case 'setBucket':
						if (tempBucketData?.addNew) {
							const fields = service?.storage?.addNew?.fields;
							fields?.forEach(({ validation, name }) => {
								const valid = validateFormField(
									validation || {},
									tempAddNewConfig?.[name] || ''
								);
								if (!valid?.valid) {
									errors.push({
										name,
										message: valid?.message || ''
									});
								}
							});
						} else {
							const fields = service?.storage?.fields;
							fields?.forEach(({ validation, name }) => {
								const valid = validateFormField(
									validation || {},
									tempBucketConfig?.[name]
								);
								if (!valid?.valid) {
									errors.push({
										name,
										message: valid?.message || ''
									});
								}
							});
						}
						break;
					default:
						break;
				}
				break;
			default:
				break;
		}

		setErrorFields(errors);

		if (errors?.length === 0) return true;
		return false;
	};

	/**
	 * It validates the data entered in the current page.
	 * If the data is valid, it verifies the credentials or chosen bucket.
	 * If the data is invalid, it displays the error messages.
	 * @returns {boolean} If the data is valid and verification is successful, it returns true, otherwise false.
	 */
	const checkAndValidate = async () => {
		const valid = validateData();
		if (valid) {
			let connected = false;

			switch (current) {
				case 'choose_provider':
					setConfig({
						...config,
						disabled: {
							next: false,
							back: false
						}
					});
					return true;
				case 'configure_credentials':
					switch (currentConfigurePage) {
						case 'setCredentials':
							setConfig({
								...config,
								verifyCredentials: true,
								verifyMessage: wp.i18n.__(
									'Credentials',
									'media-cloud-sync'
								),
								disabled: {
									next: true,
									back: true
								}
							});

							await fetchWP
								.post('verifyCredentials', {
									service: tempService,
									configSource: tempConfigSource,
									config:
										tempConfigSource === 'config'
											? {}
											: tempConfig
								})
								.then((response) => {
									if (response.success) {
										setConfig({
											...config,
											verifyCredentials: false,
											verifyBucket: false,
											showListBucketValidateMessage:
												!response?.buckets_data?.status,
											listBucketValidateMessage:
												response?.buckets_data?.message,
											disabled: {
												next: false,
												back: false
											}
										});
										if (response?.buckets_data?.status) {
											dispatch(
												setTemporaryData({
													...tempData,
													buckets:
														response?.buckets_data
															?.buckets
												})
											);
										}
										connected = true;
									} else {
										toast.error(response.message, {
											autoClose: 4000
										});
										setConfig({
											...config,
											verifyCredentials: false,
											disabled: {
												next: false,
												back: false
											}
										});
									}
								})
								.catch(() => {
									toast.error(
										wp.i18n.__(
											'Something went wrong. Please try again.',
											'media-cloud-sync'
										),
										{ autoClose: 4000 }
									);
									setConfig({
										...config,
										verifyCredentials: false,
										disabled: {
											next: false,
											back: false
										}
									});
								});
							return connected;
						case 'setBucket':
							setConfig({
								...config,
								disabled: {
									next: true,
									back: true
								}
							});
							return true;
						default:
							return true;
					}
				default:
					return true;
			}
		} else {
			switch (current) {
				case 'choose_provider':
					setConfig({
						...config,
						showProviderValidateMessage: true
					});
					break;
				case 'configure_credentials':
					switch (currentConfigurePage) {
						case 'setCredentials':
							setConfig({
								...config,
								showCredentialsValidateMessage: true
							});
							break;
						case 'setBucket':
							setConfig({
								...config,
								showBucketValidateMessage: true
							});
							break;
						default:
					}
					break;
				default:
					break;
			}

			return false;
		}
	};

	useEffect(() => {
		if (config?.showCredentialsValidateMessage) {
			validateData();
		}
	}, [tempConfig]);
	useEffect(() => {
		if (config?.showBucketValidateMessage) {
			validateData();
		}
	}, [tempBucketConfig]);

	const saveSettings = () => {
		setSaveLoader(true);
		let newBucketConfig = {};
		if (tempBucketData?.addNew) {
			newBucketConfig = {
				...bucketDefaultConfig?.[tempService]
			};
			Object.keys(newBucketConfig).forEach((key) => {
				if (
					Object.prototype.hasOwnProperty.call(tempAddNewConfig, key)
				) {
					newBucketConfig[key] = tempAddNewConfig[key];
				}
			});
		} else {
			newBucketConfig = {
				...tempBucketConfig
			};
		}

		const configToSave = tempConfigSource === 'config' ? {} : tempConfig;

		// Merge in the connection's real current settings only when reconfiguring the
		// SAME already-connected service — a first-time setup or a mid-wizard provider
		// switch has no matching current settings to merge (currentSettings would
		// belong to a different provider's schema), so those keep defaults-only.
		const settingsToSave =
			tempService === savedCredentials?.service
				? { ...settingsDefault?.[tempService], ...currentSettings }
				: settingsDefault?.[tempService];

		fetchWP
			.post('saveConfig', {
				action: 'all',
				service: tempService,
				serviceLabel: tempServiceLabel,
				cdn: tempCdn,
				config: configToSave,
				configSource: tempConfigSource,
				bucketConfig: newBucketConfig,
				settings: settingsToSave,
				// A provider switch starts from that provider's defaults; otherwise saves merge.
				replace_settings: tempService !== savedCredentials?.service
			})
			.then((response) => {
				if (response.success) {
					dispatch(
						setCredentials({
							service: tempService,
							serviceLabel: tempServiceLabel,
							cdn: tempCdn,
							config: configToSave,
							configSource: tempConfigSource,
							bucketConfig: newBucketConfig,
							security: tempSecurity,
							serviceEnabled: true,
							serviceError: ''
						})
					);
					dispatch(setSettingsData(settingsToSave));
					dispatch(
						setStatus({
							key: 'cdnRead',
							value: {
								status: false,
								message: '',
								lastChecked: null
							}
						})
					);
					dispatch(
						setStatus({
							key: 'storageCredentials',
							value: {
								status: false,
								message: '',
								lastChecked: null
							}
						})
					);
					toast.success(response.message, {
						autoClose: 3000
					});
					navigate('/choose_cdn', { state: { setup: true } });
				} else {
					toast.error(response.message, {
						autoClose: 4000
					});
				}
				setSaveLoader(false);
			})
			.catch(() => {
				toast.error(
					wp.i18n.__(
						'Something went wrong. Please try again.',
						'media-cloud-sync'
					),
					{ autoClose: 4000 }
				);
				setSaveLoader(false);
			});
	};

	const handleBack = async () => {
		const currentIndex = pageSlugs.indexOf(current);
		if (
			currentIndex === 2 &&
			configurePageSlugs.indexOf(currentConfigurePage) > 0
		) {
			setCurrentConfigurePage(
				configurePageSlugs?.[
					configurePageSlugs.indexOf(currentConfigurePage) - 1
				]
			);
		} else if (currentIndex !== 0) {
			setCurrent(pageSlugs[currentIndex - 1]);
		}
		setConfig({
			...config,
			disabled: {
				next: false,
				back: false
			}
		});
	};

	const handleNext = async () => {
		const currentIndex = pageSlugs.indexOf(current);
		let proceedAction = false;
		proceedAction = await checkAndValidate();
		if (proceedAction) {
			if (currentIndex === 2) {
				if (
					configurePageSlugs.indexOf(currentConfigurePage) + 1 <
					configurePageSlugs?.length
				) {
					setCurrentConfigurePage(
						configurePageSlugs?.[
							configurePageSlugs.indexOf(currentConfigurePage) + 1
						]
					);
				} else {
					setCurrent(pageSlugs[currentIndex + 1]);
				}
			} else if (currentIndex !== pageSlugs.length - 1) {
				setCurrent(pageSlugs[currentIndex + 1]);
			} else if (currentIndex === pageSlugs.length - 1) {
				saveSettings();
			}
		}
	};

	const {
		disabled,
		verifyCredentials,
		verifyMessage,
		showProviderValidateMessage,
		showCredentialsValidateMessage,
		showBucketValidateMessage,
		showListBucketValidateMessage,
		listBucketValidateMessage
	} = config;

	const pages = {
		welcome: Welcome,
		choose_provider: Provider,
		configure_credentials: {
			setCredentials: SetCredentials,
			setBucket: SetBucket,
			verifyPermissions: VerifyPermissions,
			security: SetSecurity
		},
		save_settings: SaveSettings
	};

	// Render datas
	const RenderPage =
		current === 'configure_credentials'
			? pages?.configure_credentials?.[currentConfigurePage]
			: pages?.[current];

	let renderProps = {};
	let footerButtonLabels = {};
	switch (current) {
		case 'choose_provider':
			footerButtonLabels = footerConfig?.[current]?.labels;
			renderProps = {
				setConfig,
				showErrors: showProviderValidateMessage,
				errors: errorFields
			};
			break;
		case 'configure_credentials':
			switch (currentConfigurePage) {
				case 'setCredentials':
					footerButtonLabels =
						footerConfig?.[current]?.labels?.connect;
					renderProps = {
						showErrors: showCredentialsValidateMessage,
						errors: errorFields
					};
					break;
				case 'setBucket':
					footerButtonLabels =
						footerConfig?.[current]?.labels?.bucket;
					renderProps = {
						showErrors: showBucketValidateMessage,
						errors: errorFields,
						listBucketValidateMessage,
						showListBucketValidateMessage
					};
					break;
				case 'verifyPermissions':
					footerButtonLabels =
						footerConfig?.[current]?.labels?.checkPermission;
					renderProps = {
						setConfig,
						handleNext
					};
					break;
				case 'security':
					footerButtonLabels =
						footerConfig?.[current]?.labels?.security;
					renderProps = {
						setConfig
					};
					break;
				default:
			}
			break;
		default:
			footerButtonLabels = footerConfig?.[current]?.labels;
	}

	const currentService = services?.[tempService];
	const currentStep = progressBarItems?.find((item) => item?.slug === current);
	const heading =
		current === 'configure_credentials'
			? currentStep?.subSteps?.[currentConfigurePage] || {}
			: currentStep || {};
	const bucketName = tempBucketData?.addNew
		? tempAddNewConfig?.bucket_name
		: tempBucketConfig?.bucket_name;
	const docs = currentService?.documentation;
	const docsLink = docs?.link
		? `<a href="${escapeHtml(docs.link)}"${
				docs?.external ? ' target="_blank" rel="noreferrer"' : ''
		  }>${wp.i18n.__('documentation', 'media-cloud-sync')}</a>`
		: wp.i18n.__('documentation', 'media-cloud-sync');
	const fillText = (text = '', html = false) =>
		text
			.replace('<servicename>', escapeHtml(tempServiceLabel || currentService?.label || ''))
			.replace('<storage>', escapeHtml(currentService?.storage?.label || ''))
			.replace(
				'<bucket>',
				html
					? `<b>${escapeHtml(bucketName || '')}</b>`
					: bucketName || ''
			)
			.replace('<documentation_link>', html ? docsLink : '');

	// Finished steps show what was chosen in the rail.
	const railValues = {
		choose_provider: currentService ? (
			<>
				{currentService?.icon ? <img src={currentService.icon} alt="" /> : null}
				{tempServiceLabel || currentService?.label}
			</>
		) : null,
		configure_credentials:
			[bucketName, tempConfig?.region].filter(Boolean).join(' · ') || null,
		save_settings: wizard?.saved
	};

	const stepLogo =
		current === 'save_settings' ? (
			<span className={style.successLogo}>
				<WizardIcon name="check" size={26} strokeWidth={2.6} />
			</span>
		) : current === 'configure_credentials' && currentService?.icon ? (
			<img src={currentService.icon} alt="" />
		) : null;

	return (
		<WizardShell
			steps={progressBarItems}
			current={current}
			subOrder={configurePageSlugs || []}
			currentSub={currentConfigurePage}
			values={railValues}
			exit={
				isConfigured
					? {
							to: '/settings',
							label: wp.i18n.__('Back to Settings', 'media-cloud-sync')
					  }
					: {
							to: '/',
							label: wp.i18n.__('Exit to Dashboard', 'media-cloud-sync')
					  }
			}
			logo={stepLogo}
			title={fillText(heading?.title)}
			desc={fillText(heading?.desc, true)}
			help={
				// Plugin guide until a provider is picked, then the provider's quick start.
				['welcome', 'choose_provider'].includes(current) || !docs?.link
					? { ...wizard?.help, text: wizard?.help?.generalText }
					: {
							...wizard?.help,
							text: fillText(wizard?.help?.text),
							url: docs.link
					  }
			}
			footer={
				<Footer
					labels={footerButtonLabels}
					display={footerConfig?.[current]?.display}
					onBack={handleBack}
					onNext={handleNext}
					loading={saveLoader}
					disabled={disabled}
				/>
			}
		>
			{current === 'configure_credentials' && verifyCredentials ? (
				<LoadingPopup
					title={`${wp.i18n.__('Verifying', 'media-cloud-sync')} ${
						currentService?.label
					} ${verifyMessage}`}
					description={wp.i18n.__(
						'This only takes a few seconds.',
						'media-cloud-sync'
					)}
				/>
			) : (
				<RenderPage {...renderProps} />
			)}
		</WizardShell>
	);
}
