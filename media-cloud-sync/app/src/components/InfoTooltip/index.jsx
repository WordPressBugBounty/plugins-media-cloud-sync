import React from 'react';
import style from './style.scss';

export default function InfoTooltip({ text }) {
	if (!text) return null;

	return (
		<span className={style.infoTooltip} tabIndex={0}>
			<svg
				width="16"
				height="16"
				viewBox="0 0 24 24"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
				<circle
					cx="12"
					cy="12"
					r="10"
					stroke="currentColor"
					strokeWidth="2"
				/>
				<path
					d="M12 11V16"
					stroke="currentColor"
					strokeWidth="2"
					strokeLinecap="round"
				/>
				<circle cx="12" cy="8" r="1" fill="currentColor" />
			</svg>
			<span className={style.tooltipText}>{text}</span>
		</span>
	);
}
