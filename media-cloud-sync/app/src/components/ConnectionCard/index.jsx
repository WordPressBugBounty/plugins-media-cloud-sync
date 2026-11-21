import React from 'react';

import { useSelector } from 'react-redux';
import { PrimaryButton } from '../Buttons';
import ProLock from '../ProLock';
import style from './style.scss';
import imagePlaceHolder from '../../images/icons/uploaded.svg';
import getSchema from '../../schema';
import { ClassNames } from '../../helper';
import Slot from '../Slot';

// Sidebar storage card: provider, bucket, status and key delivery settings.
export default function ConnectionCard() {
	const services = getSchema('services');
	const cdnServers = getSchema('cdnServers');
	const savedCredentials = useSelector((state) => state?.credentials);
	const settings = useSelector((state) => state?.common?.settings) || {};
	const { service, serviceLabel, cdn, bucketConfig, config, serviceEnabled, serviceError } =
		savedCredentials;
	const currentService = services?.[service] || services?.s3;
	const hasService = !!service;
	const operational = hasService && serviceEnabled !== false;
	const hasError = hasService && !operational;
	const privateSupport = !!cdnServers?.[service]?.[cdn?.service]?.privateSupport;

	const onOff = (value) =>
		value
			? wp.i18n.__('On', 'media-cloud-sync')
			: wp.i18n.__('Off', 'media-cloud-sync');

	const consoleLink =
		currentService?.consoleLink
			?.replace('<region>', config?.region || '')
			?.replace('<bucket_name>', bucketConfig?.bucket_name) || '#';

	return (
		<section className={ClassNames([style.connect, hasError && style.connectHasIssue])}>
			<div className={style.head}>
				<span className={style.eyebrow}>{wp.i18n.__('Storage', 'media-cloud-sync')}</span>
				{hasService ? (
					<span className={ClassNames([style.status, hasError && style.statusWarning])}>
						<span className={style.dot} />
						{operational
							? wp.i18n.__('Connected', 'media-cloud-sync')
							: wp.i18n.__('Needs attention', 'media-cloud-sync')}
					</span>
				) : null}
			</div>

			<div className={style.provider}>
				<span className={style.imgSec}>
					<img src={hasService ? currentService?.icon : imagePlaceHolder} alt="" />
				</span>
				{hasService ? (
					<span className={style.bucketInfo}>
						<span className={style.bucketLabel}>
							{serviceLabel || currentService?.label}
						</span>
						<a href={consoleLink} target="_blank" rel="noreferrer">
							{bucketConfig?.bucket_name}
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<path
									d="M9 5h10v10M19 5 6 18"
									stroke="currentColor"
									strokeWidth="2.2"
									strokeLinecap="round"
									strokeLinejoin="round"
								/>
							</svg>
						</a>
					</span>
				) : (
					<span className={style.noService}>
						<strong>{wp.i18n.__('Connect to cloud', 'media-cloud-sync')}</strong>
						<span>
							{wp.i18n.__(
								'Get started with syncing your media files to the cloud.',
								'media-cloud-sync'
							)}
						</span>
					</span>
				)}
			</div>

			{!hasService ? (
				<PrimaryButton
					label={wp.i18n.__('Configure', 'media-cloud-sync')}
					link="/configure"
				/>
			) : null}

			{hasService && !hasError ? (
				<dl className={style.details}>
					<div>
						<dt>{wp.i18n.__('Delivery', 'media-cloud-sync')}</dt>
						<dd>
							{settings?.rewrite_url && cdn?.label
								? cdn.label
								: onOff(!!settings?.rewrite_url)}
						</dd>
					</div>
					<div>
						<dt>{wp.i18n.__('Remove files from server', 'media-cloud-sync')}</dt>
						<dd>{onOff(!!settings?.remove_from_server)}</dd>
					</div>
					{privateSupport ? (
						<div>
							<dt>{wp.i18n.__('Private media', 'media-cloud-sync')}</dt>
							<dd>
								<Slot name="connectionCard.privateMedia" settings={settings}>
									<ProLock />
								</Slot>
							</dd>
						</div>
					) : null}
				</dl>
			) : null}

			{hasError ? (
				<div className={style.connectAlert} role="alert">
					<p className={style.connectAlertTitle}>
						{wp.i18n.__('Storage is not fully configured', 'media-cloud-sync')}
					</p>
					<p className={style.connectAlertMessage} title={serviceError || ''}>
						{serviceError ||
							wp.i18n.__(
								'Review your storage credentials and bucket configuration.',
								'media-cloud-sync'
							)}
					</p>
					<PrimaryButton
						variant="corner-warning"
						label={wp.i18n.__('Configure', 'media-cloud-sync')}
						link="/configure"
					/>
				</div>
			) : null}
		</section>
	);
}
