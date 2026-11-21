import React from 'react';

import style from './style.scss';
import ToggleButton from '../ToggleButton';
import TextInput from '../TextInput';
import SelectInput from '../SelectInput';
import ListInput from '../ListInput';
import ChecklistInput from '../ChecklistInput';

// Renders schema options (toggle, text, number, password, select, list, checklist) — used by job start dialogs and tool pages. `showWhen: {field, value}` shows one only for that value.
export default function SchemaOptions({ options = [], values = {}, hints = {}, idPrefix = '', onChange }) {
	return (options || []).map((option) => {
		const name = `${idPrefix}_${option.name}`;
		const set = (fieldName, value) => onChange(option.name, value);

		if (option.showWhen && values[option.showWhen.field] !== option.showWhen.value) {
			return null;
		}
		if (option.type === 'checklist') {
			return (
				<ChecklistInput
					key={option.name}
					name={name}
					label={option.label}
					options={option.options || []}
					value={values[option.name] || []}
					onChange={set}
				/>
			);
		}

		if (option.type === 'select') {
			return (
				<SelectInput
					key={option.name}
					label={option.label}
					name={name}
					options={option.options || []}
					value={values[option.name] ?? ''}
					onChange={set}
				/>
			);
		}
		if (option.type === 'list') {
			return (
				<ListInput
					key={option.name}
					name={name}
					label={option.label}
					tooltip={option.desc}
					placeholder={option.placeholder}
					value={values[option.name] || []}
					onChange={set}
				/>
			);
		}
		if (['text', 'number', 'password'].includes(option.type)) {
			return (
				<TextInput
					key={option.name}
					type={option.type}
					min={option.min}
					label={option.label}
					tooltip={option.desc}
					name={name}
					placeholder={option.placeholder}
					value={values[option.name] ?? ''}
					hint={hints?.[option.name]}
					compact
					onChange={set}
				/>
			);
		}
		return (
			<div key={option.name} className={option.width === 'half' ? null : style.toggleRow}>
				<ToggleButton
					label={option.label}
					name={name}
					value={!!values[option.name]}
					onChange={set}
					size="small"
				/>
				{!values[option.name] && option.offWarning ? (
					<div className={style.offWarning}>
						<span className={style.warningIcon}>
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<path d="M12 3.5 21.5 20H2.5L12 3.5Z" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
								<path d="M12 9.5v5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
								<circle cx="12" cy="17" r="1" fill="currentColor" />
							</svg>
						</span>
						<span>{option.offWarning}</span>
					</div>
				) : null}
			</div>
		);
	});
}
