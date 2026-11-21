import React from 'react';
import { useSelector } from 'react-redux';

import style from './style.scss';
import getSchema from '../../schema';
import WizardIcon from '../../components/WizardShell/icons';

// Summary of the connection about to be saved.
export default function SaveSettings() {
	const services = getSchema('services');
	const wizard = getSchema('wizard') || {};
	const { service, serviceLabel, config, configSource, bucketData } = useSelector(
		(state) => state?.tempCredentials
	);
	const currentService = services?.[service];
	const regionField = currentService?.fields?.find((field) => field?.name === 'region');
	const regionLabel =
		regionField?.options?.find((option) => option?.value === config?.region)?.label ||
		config?.region;
	const bucketName = bucketData?.addNew
		? bucketData?.addNewConfig?.bucket_name
		: bucketData?.config?.bucket_name;

	const rows = [
		{
			icon: 'cloud',
			label: wp.i18n.__('Provider', 'media-cloud-sync'),
			value: (
				<>
					{currentService?.icon ? <img src={currentService.icon} alt="" /> : null}
					{serviceLabel || currentService?.label}
				</>
			)
		},
		{
			icon: configSource === 'config' ? 'code' : 'database',
			label: wp.i18n.__('Credentials', 'media-cloud-sync'),
			value:
				configSource === 'config'
					? wp.i18n.__('wp-config.php constant', 'media-cloud-sync')
					: wp.i18n.__('Stored in database', 'media-cloud-sync')
		},
		configSource !== 'config' && regionLabel
			? {
					icon: 'map',
					label: wp.i18n.__('Region', 'media-cloud-sync'),
					value: regionLabel
			  }
			: null,
		bucketName
			? {
					icon: bucketData?.addNew ? 'databasePlus' : 'database',
					label: currentService?.storage?.label,
					value: bucketData?.addNew
						? `${bucketName} (${wp.i18n.__('new', 'media-cloud-sync')})`
						: bucketName
			  }
			: null
	].filter(Boolean);

	return (
		<div className={style.saveSettingsPage}>
			<div className={style.summary}>
				{rows.map((row) => (
					<div className={style.summaryRow} key={row.label}>
						<WizardIcon name={row.icon} size={17} />
						<span className={style.summaryLabel}>{row.label}</span>
						<strong>{row.value}</strong>
					</div>
				))}
			</div>
			{wizard?.saveNote ? (
				<div className={style.note}>
					<WizardIcon name="info" size={16} />
					{wizard.saveNote}
				</div>
			) : null}
		</div>
	);
}
