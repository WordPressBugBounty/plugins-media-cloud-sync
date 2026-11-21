import React from 'react';
import { toast } from 'react-toastify';
import style from './style.scss';
import FileInput from '../FileInput';
import TextareaInput from '../TextareaInput';

export default function jsonReader({
	onChange,
	label = '',
	name = '',
	placeholder = '',
	value = {},
	error = false,
	errorMessage = ''
}) {
	const handleFileUpload = async (event) => {
		const file = event.target.files[0];
		if (file) {
			try {
				const text = await file.text();
				// Try to parse and pretty-print the JSON
				const parsed = JSON.parse(text);
				const formatted = JSON.stringify(parsed, null, 4);
				onChange(name, formatted);
			} catch {
				toast.error(
					wp.i18n.__(
						'The selected file is not valid JSON.',
						'media-cloud-sync'
					),
					{ autoClose: 4000 }
				);
			}
		}
	};

	return (
		<div className={style.jsonReader}>
			<TextareaInput
				label={label}
				name={name}
				placeholder={placeholder}
				value={value}
				error={error}
				errorMessage={errorMessage}
				onChange={onChange}
			/>
			<FileInput
				name={`${name}_json`}
				accept="application/json"
				value={value}
				onChange={handleFileUpload}
				placeholder={wp.i18n.__(
					'Read JSON from file',
					'media-cloud-sync'
				)}
			/>
		</div>
	);
}
