import React, { useRef, useState } from 'react';
import style from './style.scss';
import { ClassNames } from '../../helper';

export default function TagsInput({
	name,
	label = '',
	value = [],
	placeholder = '',
	error = false,
	errorMessage = '',
	onChange
}) {
	const [text, setText] = useState('');
	const inputRef = useRef(null);

	const commit = () => {
		const cleaned = text.trim().replace(/^\./, '').toLowerCase();
		if (!cleaned) {
			return;
		}
		if (value.some((tag) => tag.toLowerCase() === cleaned)) {
			setText('');
			return;
		}
		onChange(name, [...value, cleaned]);
		setText('');
	};

	const removeAt = (index) => {
		onChange(
			name,
			value.filter((_, i) => i !== index)
		);
	};

	const handleKeyDown = (e) => {
		if (e.key === 'Enter' || e.key === ',') {
			e.preventDefault();
			commit();
		} else if (e.key === 'Backspace' && !text && value.length) {
			removeAt(value.length - 1);
		}
	};

	return (
		<div className={style.input}>
			{label ? <label htmlFor={name}>{label}</label> : null}
			{/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */}
			<div
				className={ClassNames([style.field, error ? style.hasError : null])}
				onClick={() => inputRef.current?.focus()}
			>
				{value.map((tag, index) => (
					<span className={style.tag} key={tag}>
						{tag}
						<button
							type="button"
							className={style.remove}
							onClick={() => removeAt(index)}
							aria-label={`Remove ${tag}`}
						>
							&times;
						</button>
					</span>
				))}
				<input
					ref={inputRef}
					className="mcs-tag-entry"
					type="text"
					id={name}
					value={text}
					placeholder={value.length ? '' : placeholder}
					onChange={({ target }) => setText(target.value)}
					onKeyDown={handleKeyDown}
					onBlur={commit}
				/>
			</div>
			{error ? <p className={style.error}>{errorMessage}</p> : null}
		</div>
	);
}
