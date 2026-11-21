import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { defaultTemporaryActions } from '../../redux/slices/tempCredential';

import style from './style.scss';
import getSchema from '../../schema';
import { ClassNames, copyToClipboard } from '../../helper';
import SelectInput from '../../components/SelectInput';
import TextInput from '../../components/TextInput';
import ToggleButton from '../../components/ToggleButton';
import JsonReader from '../../components/JsonReader';
import OptionCard, { OptionGroup } from '../../components/OptionCard';
import WizardIcon from '../../components/WizardShell/icons';

const CONFIG_CONSTANT_PLACEHOLDER = 'WPMCS_CONFIG';

// Icon per credential source.
const MODE_ICONS = {
	database: 'database',
	config: 'code',
	existing: 'link'
};

export default function SetCredentials({
	showErrors,
	errors,
	slice = 'tempCredentials',
	actions = defaultTemporaryActions,
	// Pass a different constant name (e.g. WPMCS_BTB_CONFIG) to swap it into the schema's instructions/snippet.
	wpConfigConstant = CONFIG_CONSTANT_PLACEHOLDER,
	// Service slug of the site's already-connected primary connection (e.g. Bucket-to-Bucket's
	// destination form). When the destination's chosen provider matches it, a third "Use
	// Existing" mode is offered — same provider, same credentials, without re-entering them.
	existingConnectionService = ''
}) {
	const dispatch = useDispatch();
	const services = getSchema('services');
	const defaultValues = getSchema('configureDefaultValues');
	const service = useSelector((state) => state?.[slice]?.service);
	const config = useSelector((state) => state?.[slice]?.config);
	const configSource = useSelector(
		(state) => state?.[slice]?.configSource
	);
	const currentService = services?.[service];
	const regionField = currentService?.fields?.find(
		(field) => field?.name === 'region' && field?.type === 'select'
	);
	// No-op when wpConfigConstant is still the default, so onboarding's wizard is unaffected.
	const withConstantName = (text) =>
		typeof text === 'string' && wpConfigConstant !== CONFIG_CONSTANT_PLACEHOLDER
			? text.split(CONFIG_CONSTANT_PLACEHOLDER).join(wpConfigConstant)
			: text;
	const [copied, setCopied] = useState(false);
	const [regionCopied, setRegionCopied] = useState(false);
	const [selectedRegion, setSelectedRegion] = useState('');

	useEffect(() => {
		const defaultRegionOption =
			regionField?.options?.find((option) => option?.default) ??
			regionField?.options?.[0];
		setSelectedRegion(defaultRegionOption?.value ?? '');
		setRegionCopied(false);
	}, [service]);

	useEffect(() => {
		dispatch(
			actions.setTemporaryConfig({
				...defaultValues?.[currentService?.slug],
				...config
			})
		);
	}, []);

	const handleChange = (key, value) => {
		dispatch(
			actions.setTemporaryConfig({
				...config,
				[key]: value
			})
		);
	};

	const handleCopy = () => {
		const snippet = withConstantName(currentService?.configSource?.config?.snippet) || '';
		copyToClipboard(snippet)
			.then(() => {
				setCopied(true);
				setTimeout(() => setCopied(false), 2000);
			})
			.catch(() => {});
	};

	const regionDisplayValue =
		selectedRegion === '' ? "''" : selectedRegion;

	const handleCopyRegion = () => {
		const matchedOption = regionField?.options?.find(
			(option) => option?.value === selectedRegion
		);
		const regionValue =
			matchedOption !== undefined
				? matchedOption.value
				: selectedRegion ?? '';

		copyToClipboard(regionValue)
			.then(() => {
				setRegionCopied(true);
				setTimeout(() => setRegionCopied(false), 2000);
			})
			.catch(() => {});
	};

	const sourceModeOrder = ['database', 'config'];
	const existingModeAvailable =
		!!existingConnectionService && existingConnectionService === service;
	const sourceModes = [
		...sourceModeOrder
			.filter((value) => currentService?.configSource?.[value])
			.map((value) => ({
				value,
				...currentService.configSource[value],
				description: withConstantName(currentService.configSource[value]?.description)
			})),
		...(existingModeAvailable
			? [
					{
						value: 'existing',
						title: wp.i18n.__('Use Existing', 'media-cloud-sync'),
						description: wp.i18n.__(
							'Reuse the credentials from your currently connected account — no need to re-enter them. Pick a different bucket in the next step.',
							'media-cloud-sync'
						)
					}
				]
			: [])
	];
	const sourceContent = currentService?.configSource?.[configSource] ?? {};
	const wpConfigContent = currentService?.configSource?.config ?? {};
	const modeDescription =
		configSource === 'existing'
			? sourceModes.find((mode) => mode.value === 'existing')?.description
			: withConstantName(sourceContent?.description);
	const wpConfigInstructions = (
		wpConfigContent?.instructions?.length
			? wpConfigContent.instructions
			: wpConfigContent?.instruction
				? [wpConfigContent.instruction]
				: []
	).map(withConstantName);
	const wpConfigSnippet = withConstantName(wpConfigContent?.snippet);

	return (
		<div className={style.credentialsPage}>
			{sourceModes?.length > 1 ? (
				<OptionGroup>
					{sourceModes?.map((mode) => (
						<OptionCard
							key={mode?.value}
							icon={MODE_ICONS[mode?.value] || 'database'}
							title={mode?.title}
							description={mode?.description}
							tag={
								mode?.recommended
									? wp.i18n.__('Recommended', 'media-cloud-sync')
									: ''
							}
							active={configSource === mode?.value}
							onSelect={() =>
								dispatch(actions.setTemporaryConfigSource(mode?.value))
							}
						/>
					))}
				</OptionGroup>
			) : null}

			<div className={style.sourceModeContent}>
				{modeDescription && sourceModes?.length <= 1 ? (
					<p className={style.sourceModeDescription}>
						{modeDescription}
					</p>
				) : null}

			{configSource === 'existing' && (
				<p className={style.note}>
					{wp.i18n.__(
						'No credentials to enter — this destination will reuse your currently connected account.',
						'media-cloud-sync'
					)}
				</p>
			)}

			{configSource === 'config' && (
				<div className={style.wpConfigSource}>
					{wpConfigInstructions.map((instruction, index) => (
						<p
							className={style.instruction}
							key={`wp-config-instruction-${index}`}
						>
							{instruction}
						</p>
					))}
					<div className={style.codeBlock}>
						<button
							type="button"
							className={style.copyButton}
							onClick={handleCopy}
						>
							<WizardIcon
								name={copied ? 'check' : 'copy'}
								size={13}
								strokeWidth={copied ? 2.4 : 1.8}
							/>
							{copied
								? wp.i18n.__('Copied!', 'media-cloud-sync')
								: wp.i18n.__('Copy', 'media-cloud-sync')}
						</button>
						<pre>
							<code>{wpConfigSnippet}</code>
						</pre>
					</div>
					{regionField?.options?.length ? (
						<div className={style.regionPicker}>
							<label
								className={style.regionPickerLabel}
								htmlFor="wpmcs_region_value"
							>
								{regionField.label}
							</label>
							{wpConfigContent?.regionHint ? (
								<p className={style.regionPickerHint}>
									{wpConfigContent.regionHint}
								</p>
							) : null}
							<div className={style.regionPickerRow}>
								<SelectInput
									name="wpmcs_region_value"
									icon={regionField?.icon}
									value={selectedRegion}
									options={regionField.options}
									onChange={(_name, value) =>
										setSelectedRegion(value ?? '')
									}
								/>
								<code className={style.regionValue}>
									{regionDisplayValue}
								</code>
								<button
									type="button"
									className={ClassNames([
										style.regionCopy,
										regionCopied ? style.copied : null
									])}
									onClick={handleCopyRegion}
									aria-label={wp.i18n.__(
										'Copy region value',
										'media-cloud-sync'
									)}
								>
									<WizardIcon
										name={regionCopied ? 'check' : 'copy'}
										size={15}
										strokeWidth={regionCopied ? 2.4 : 1.8}
									/>
									<span role="status">
										{regionCopied
											? wp.i18n.__('Copied!', 'media-cloud-sync')
											: wp.i18n.__('Copy', 'media-cloud-sync')}
									</span>
								</button>
							</div>
						</div>
					) : null}
					{wpConfigContent?.note ? (
						<p className={style.note}>{wpConfigContent.note}</p>
					) : null}
				</div>
			)}

			{configSource !== 'existing' && configSource !== 'config' && (
				<div className={style.fields}>
					{currentService?.fields?.map((field) => {
						const error = errors?.find(
							(err) => err.name === field?.name
						);
						switch (field?.type) {
							case 'select':
								return (
									<SelectInput
										type="text"
										label={field?.label}
										icon={field?.icon}
										name={field?.name}
										key={field?.name}
										options={field?.options}
										placeholder={field?.placeholder}
										value={config?.[field?.name]}
										error={showErrors && error}
										errorMessage={error?.message}
										onChange={handleChange}
									/>
								);
							case 'toggle':
								return (
									<ToggleButton
										label={field?.label}
										icon={field?.icon}
										name={field?.name}
										key={field?.name}
										value={config?.[field?.name]}
										onChange={handleChange}
									/>
								);
							case 'jsonReader':
								return (
									<JsonReader
										label={field?.label}
										icon={field?.icon}
										name={field?.name}
										key={field?.name}
										placeholder={field?.placeholder}
										value={config?.[field?.name]}
										error={showErrors && error}
										errorMessage={error?.message}
										onChange={handleChange}
									/>
								);
							default:
								return (
									<TextInput
										type="text"
										label={field?.label}
										icon={field?.icon}
										name={field?.name}
										key={field?.name}
										placeholder={field?.placeholder}
										value={config?.[field?.name]}
										error={showErrors && error}
										errorMessage={error?.message}
										onChange={handleChange}
									/>
								);
						}
					})}
				</div>
			)}
			</div>
		</div>
	);
}
