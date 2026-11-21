import React from 'react';
import style from './style.scss';
import { ClassNames } from '../../helper';

export default function FileInput({
	label = '',
	name,
	placeholder = '',
	accept = '',
	value = {},
	onChange
}) {
	return (
		<div className={style.input}>
			<label htmlFor={name}>{label}</label>
			<input
				type="file"
				name={name}
				id={name}
				accept={accept}
				onChange={onChange}
			/>
			<label htmlFor={name} className={style.fileInput}>
				<div
					className={ClassNames([
						style.file,
						value?.name ? style.active : ''
					])}
				>
					{value?.name || placeholder}
				</div>
			</label>
		</div>
	);
}
