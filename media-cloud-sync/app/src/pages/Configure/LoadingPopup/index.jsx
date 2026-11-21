import React from 'react';
import style from './style.scss';

// Inline "verifying" card shown in the step body while a check runs.
export default function LoadingPopup({ title = '', description = '' }) {
	return (
		<div className={style.loadingPopup} role="status" aria-live="polite">
			<span className={style.spinner} />
			<div>
				<div className={style.title}>{title}</div>
				{description ? <p>{description}</p> : null}
			</div>
		</div>
	);
}
