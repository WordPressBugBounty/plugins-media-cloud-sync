import React from 'react';
import style from './style.scss';

// A labelled list of checkboxes; the value is the array of checked option values.
export default function ChecklistInput({ name, label = '', options = [], value = [], onChange }) {
	const checked = Array.isArray(value) ? value : [];
	const toggle = (optionValue) =>
		onChange(
			name,
			checked.includes(optionValue)
				? checked.filter((item) => item !== optionValue)
				: [...checked, optionValue]
		);

	return (
		<fieldset className={style.checklist}>
			{label ? <legend>{label}</legend> : null}
			<div className={style.options}>
				{options.map((option) => (
					<label key={option.value}>
						<input
							type="checkbox"
							checked={checked.includes(option.value)}
							onChange={() => toggle(option.value)}
						/>
						<span>{option.label}</span>
					</label>
				))}
			</div>
		</fieldset>
	);
}
