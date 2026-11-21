import React from 'react';
import style from './style.scss';
import getSchema from '../../schema';

// Dashboard heading from the schema `dashboard` section.
export default function WelcomeContent() {
	const dashboard = getSchema('dashboard') || {};

	return (
		<div className={style.content}>
			<h2>{dashboard.title || wp.i18n.__('Dashboard', 'media-cloud-sync')}</h2>
			{dashboard.description ? <p>{dashboard.description}</p> : null}
		</div>
	);
}
