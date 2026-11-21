import React from 'react';
import InfoTooltip from '../InfoTooltip';
import { ClassNames } from '../../helper';
import style from './style.scss';
import WizardIcon from '../WizardShell/icons';

export default function TextInput({
	type = 'text',
	label = '',
	tooltip = '',
	compact = false,
	name,
	placeholder = '',
	value = '',
	error = false,
	errorMessage = '',
	min,
	max,
	step,
	icon = '',
	hint = '',
	onChange
}) {
	return (
		<div
			className={ClassNames([
				style.input,
				compact ? style.compact : ''
			])}
		>
			{label ? (
				<div className={style.labelRow}>
					<label htmlFor={name}>{label}</label>
					<InfoTooltip text={tooltip} />
				</div>
			) : null}
			<div className={ClassNames([style.field, icon ? style.withIcon : ''])}>
			{icon ? (
				<span className={style.icon}>
					<WizardIcon name={icon} size={16} />
				</span>
			) : null}
			<input
				type={type}
				name={name}
				className={error ? style.error : null}
				value={value}
				placeholder={placeholder}
				min={min}
				max={max}
				step={step}
				autoComplete={type === 'password' ? 'new-password' : undefined}
				onChange={({ target }) => onChange(name, target?.value)}
				id={name}
			/>
			</div>
			{hint ? <p className={style.hint}>{hint}</p> : null}
			{error ? <p className={style.error}>{errorMessage}</p> : null}
		</div>
	);
}
