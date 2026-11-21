import React from 'react';
import style from './style.scss';
import WizardIcon from '../WizardShell/icons';
import { ClassNames } from '../../helper';

// Selectable card with an icon, title, optional tag and description (radio semantics).
export function OptionGroup({ children, label = '' }) {
	return (
		<div className={style.group} role="radiogroup" aria-label={label}>
			{children}
		</div>
	);
}

export default function OptionCard({
	icon,
	title,
	description = '',
	tag = '',
	active = false,
	onSelect
}) {
	return (
		<div
			className={ClassNames([style.card, active ? style.active : ''])}
			role="radio"
			aria-checked={active}
			tabIndex={0}
			onClick={onSelect}
			onKeyDown={(event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					onSelect?.();
				}
			}}
		>
			{active ? (
				<span className={style.tick}>
					<WizardIcon name="check" size={12} strokeWidth={3} />
				</span>
			) : null}
			<span className={style.icon}>
				<WizardIcon name={icon} size={19} />
			</span>
			<div className={style.text}>
				<strong>
					{title}
					{tag ? <span className={style.tag}>{tag}</span> : null}
				</strong>
				{description ? <span className={style.desc}>{description}</span> : null}
			</div>
		</div>
	);
}
