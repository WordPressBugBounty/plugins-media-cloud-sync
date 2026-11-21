import React from 'react';
import InfoTooltip from '../InfoTooltip';
import style from './style.scss';

export default function TextareaInput({
	label = '',
	tooltip = '',
	name,
	placeholder = '',
	value = '',
	error = false,
	errorMessage = '',
	onChange
}) {
	return (
		<div className={style.input}>
			{label ? (
				<div className={style.labelRow}>
					<label htmlFor={name}>{label}</label>
					<InfoTooltip text={tooltip} />
				</div>
			) : null}
			<textarea
				name={name}
				className={error ? style.error : null}
				value={value}
				placeholder={placeholder}
				onChange={({ target }) => onChange(name, target?.value)}
				id={name}
			/>
			{error ? <p className={style.error}>{errorMessage}</p> : null}
		</div>
	);
}
