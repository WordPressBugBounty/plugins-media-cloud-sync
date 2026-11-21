import React from 'react';
import style from './style.scss';
import { ClassNames } from '../../helper';

export default function ToggleButton({
	label = '',
	name,
	value = false,
	onChange,
	disabled = false,
	size = 'normal',
	error = false,
	errorMessage = ''
}) {
	return (
		<div className={ClassNames([style.input, style?.[size]])}>
			<input
				type="checkbox"
				name={name}
				id={name}
				checked={value}
				onChange={({ target }) => onChange(name, target?.checked)}
				disabled={disabled}
			/>
			<label htmlFor={name}>
				<div className={style.toggler} />
				{label ? <div className={style.label}>{label}</div> : null}
			</label>
			{error ? (
				<p className={style.error}>{errorMessage}</p>
			) : null}
		</div>
	);
}
