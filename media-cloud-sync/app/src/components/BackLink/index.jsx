import React from 'react';
import { Link } from 'react-router-dom';
import WizardIcon from '../WizardShell/icons';
import style from './style.scss';

// "‹ Back to …" link shown above a page.
export default function BackLink({ to = '/', label }) {
	return (
		<Link to={to} className={style.backLink}>
			<WizardIcon name="left" size={14} strokeWidth={2.2} />
			{label || wp.i18n.__('Back to Dashboard', 'media-cloud-sync')}
		</Link>
	);
}
