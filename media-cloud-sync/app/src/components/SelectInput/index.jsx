import React from 'react';
import Select, { components } from 'react-select';
import style from './style.scss';
import WizardIcon from '../WizardShell/icons';
import { ClassNames } from '../../helper';

const SingleValueWithCapsule = (props) => {
	const { valueCapsule } = props.selectProps;

	return (
		<components.SingleValue {...props}>
			<span className={style.singleValueWithCapsule}>
				<span className={style.singleValueLabel}>{props.children}</span>
				<span className={style.valueCapsule}>
					<code>{valueCapsule}</code>
				</span>
			</span>
		</components.SingleValue>
	);
};

// Chevron centred with the field's right padding.
const DropdownIndicator = (props) => (
	<components.DropdownIndicator {...props}>
		<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
			<path
				d="M6 9l6 6 6-6"
				stroke="currentColor"
				strokeWidth="2.2"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	</components.DropdownIndicator>
);

export default function SelectInput({
	name,
	label = '',
	value,
	placeholder = '',
	options,
	error = false,
	errorMessage = '',
	valueCapsule,
	icon = '',
	onChange
}) {
	const showValueCapsule = valueCapsule !== undefined && valueCapsule !== null;

	return (
		<div className={style.input}>
			{label ? <label htmlFor={name}>{label}</label> : null}
			<div className={ClassNames([style.field, icon ? style.withIcon : ''])}>
			{icon ? (
				<span className={style.icon}>
					<WizardIcon name={icon} size={16} />
				</span>
			) : null}
			<Select
				value={options?.find((opt) => opt.value === value)}
				defaultValue={options?.find((opt) => opt.value === value)}
				className={ClassNames([
					style.select,
					showValueCapsule ? style.selectWithCapsule : null,
					error ? 'selectError' : null
				])}
				classNamePrefix="selectInput-"
				onChange={(e) => onChange(name, e?.value ?? '')}
				placeholder={placeholder}
				options={options}
				inputId={name}
				valueCapsule={valueCapsule}
				components={{
					DropdownIndicator,
					IndicatorSeparator: null,
					...(showValueCapsule ? { SingleValue: SingleValueWithCapsule } : {})
				}}
			/>
			</div>
			{error ? <p className={style.error}>{errorMessage}</p> : null}
		</div>
	);
}
