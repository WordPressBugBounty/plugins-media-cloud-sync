import React, { useState } from 'react';

import style from './style.scss';
import InfoTooltip from '../InfoTooltip';

// Editable list of strings: one row per item with a remove button, and an input to add more.
export default function ListInput({ name, label = '', tooltip = '', placeholder = '', value = [], onChange }) {
	const [text, setText] = useState('');
	const items = Array.isArray(value) ? value : [];

	const add = () => {
		const entry = text.trim().replace(/\/+$/, '');
		if (entry && !items.includes(entry)) {
			onChange(name, [...items, entry]);
		}
		setText('');
	};

	return (
		<div className={style.listInput}>
			{label ? (
				<div className={style.labelRow}>
					<label htmlFor={name}>{label}</label>
					<InfoTooltip text={tooltip} />
				</div>
			) : null}
			{items.length ? (
				<ul className={style.rows}>
					{items.map((item) => (
						<li key={item}>
							<span title={item}>{item}</span>
							<button
								type="button"
								className={style.remove}
								onClick={() => onChange(name, items.filter((other) => other !== item))}
								aria-label={wp.i18n.sprintf(
									/* translators: %s: the list item */
									wp.i18n.__('Remove %s', 'media-cloud-sync'),
									item
								)}
							>
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<path d="M6 6l12 12M18 6 6 18" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" />
								</svg>
							</button>
						</li>
					))}
				</ul>
			) : null}
			<div className={style.addRow}>
				<input
					id={name}
					type="text"
					value={text}
					placeholder={placeholder}
					onChange={({ target }) => setText(target.value)}
					onKeyDown={(event) => {
						if (event.key === 'Enter') {
							event.preventDefault();
							add();
						}
					}}
				/>
				<button type="button" className={style.addButton} onClick={add} disabled={!text.trim()}>
					{wp.i18n.__('Add', 'media-cloud-sync')}
				</button>
			</div>
		</div>
	);
}
