import React from 'react';

import style from './style.scss';
import loaderBlue from '../../images/loader-blue.svg';

// Loading icon, with optional `text` before it where the context alone doesn't say what's loading.
export default function Spinner({ size = 22, block = false, label = '', text = '' }) {
	const img = (
		<img
			className={style.spinner}
			src={loaderBlue}
			width={size}
			height={size}
			alt={text ? '' : label || wp.i18n.__('Loading', 'media-cloud-sync')}
		/>
	);
	const content = text ? (
		<span className={style.withText}>
			{text}
			{img}
		</span>
	) : (
		img
	);
	return block ? <div className={style.block}>{content}</div> : content;
}
