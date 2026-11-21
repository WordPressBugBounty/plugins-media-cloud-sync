import React from 'react';
import style from './style.scss';
import { ClassNames } from '../../helper';

import logo from '../../images/logo.svg';

// Full-page state while the app loads its settings, or the error if that fails.
export default function MainLoader({ error = '', onRetry = null }) {
	if (error) {
		return (
			<div className={style.mainLoader}>
				<div className={style.card} role="alert">
					<span className={ClassNames([style.iconTile, style.errorTile])}>
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
							<path d="M12 3.5 21.5 20H2.5L12 3.5Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
							<path d="M12 9.5v5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
							<circle cx="12" cy="17" r="1.1" fill="currentColor" />
						</svg>
					</span>
					<h2>{wp.i18n.__('Media Cloud Sync could not load', 'media-cloud-sync')}</h2>
					<p className={style.errorMessage}>{error}</p>
					{onRetry ? (
						<button type="button" className={style.retryButton} onClick={onRetry}>
							{wp.i18n.__('Try again', 'media-cloud-sync')}
						</button>
					) : null}
				</div>
			</div>
		);
	}

	return (
		<div className={style.mainLoader}>
			<div className={style.card} aria-busy="true">
				<span className={style.logoRing}>
					<img className={style.logo} src={logo} alt="" />
				</span>
				<h2>{wp.i18n.__('Loading Media Cloud Sync', 'media-cloud-sync')}</h2>
				<p>{wp.i18n.__('Getting your storage and settings ready.', 'media-cloud-sync')}</p>
			</div>
		</div>
	);
}
