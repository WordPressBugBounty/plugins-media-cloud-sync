import React from 'react';
import { ClassNames, formatStatusLastChecked } from '../../helper';
import { CONNECTION_STATUS_CHECKS } from '../../helper/connectionStatus';
import style from './style.scss';
import Spinner from '../Spinner';

/**
 * Status line for a saved connection check: checking, passed, failed (with the full message) or not checked yet.
 */
export default function ConnectionStatusCheck({ check, status, loading, onRefresh }) {
	const meta = CONNECTION_STATUS_CHECKS[check];
	const statusState = status?.[meta.statusKey];
	const isLoading = loading?.[meta.loadingKey];
	const checked = !!statusState?.lastChecked;

	let tone = 'idle';
	let text = wp.i18n.__('Not checked yet', 'media-cloud-sync');
	if (isLoading) {
		tone = 'checking';
		text = '';
	} else if (checked && statusState?.status) {
		tone = 'passed';
		text = statusState?.message || meta.defaultMessage;
	} else if (checked) {
		tone = 'failed';
		text = meta.failedLabel;
	}

	return (
		<div className={style.check}>
			<div className={ClassNames([style.line, style[tone]])} aria-live="polite">
				{isLoading ? (
					<Spinner size={16} text={wp.i18n.__('Checking', 'media-cloud-sync')} />
				) : (
					<span className={style.dot} aria-hidden="true" />
				)}
				{text ? <span className={style.text}>{text}</span> : null}
				{checked && !isLoading ? (
					<span className={style.time}>
						{formatStatusLastChecked(statusState?.lastChecked)}
					</span>
				) : null}
				<button
					type="button"
					className={style.recheck}
					onClick={onRefresh}
					disabled={isLoading}
				>
					{checked
						? wp.i18n.__('Recheck', 'media-cloud-sync')
						: wp.i18n.__('Run check', 'media-cloud-sync')}
				</button>
			</div>
			{tone === 'failed' && statusState?.message ? (
				<div className={style.message} role="alert">
					{statusState.message}
				</div>
			) : null}
		</div>
	);
}
