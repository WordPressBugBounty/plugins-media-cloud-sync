import React from 'react';

import style from './style.scss';
import { ClassNames } from '../../../helper';
import getSchema from '../../../schema';
import ProLock from '../../ProLock';

// Small icon per method; unknown methods get none.
const ICONS = {
	ajax: (
		<path d="M13 3 5 13h6l-1 8 8-10h-6l1-8Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
	),
	cron: (
		<>
			<circle cx="12" cy="12" r="8.5" stroke="currentColor" strokeWidth="1.6" />
			<path d="M12 7.5V12l3 2" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
		</>
	),
	mixed: (
		<path d="M4 7h11l-3-3m3 3-3 3M20 17H9l3-3m-3 3 3 3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
	)
};

// Options come from the schema `syncMethods`; `allowed` (a job's own list) narrows them.
// layout 'row' puts the options side by side and shows only the selected one's description.
export default function SyncMethodToggle({
	value,
	onChange,
	disabled = false,
	allowed = null,
	layout = 'stack',
	showLabel = true
}) {
	const MODES = getSchema('syncMethods').filter(
		(mode) => !allowed?.length || allowed.includes(mode.value)
	);
	const isRow = layout === 'row';
	const activeMode = MODES.find((mode) => mode.value === value);

	return (
		<div className={ClassNames([style.syncMethodToggle, isRow ? style.row : null])} role="radiogroup" aria-label={wp.i18n.__('Sync Method', 'media-cloud-sync')}>
			{showLabel ? (
				<div className={style.label}>
					{wp.i18n.__('Sync Method', 'media-cloud-sync')}
				</div>
			) : null}
			<div className={style.options}>
				{MODES.map((mode) => {
					const isLockedOption = !!mode.preview;
					const isActive = value === mode.value;
					const isDisabled = disabled || isLockedOption;
					// Locked options aren't real buttons so the PRO badge's tooltip stays interactive.
					const Option = isLockedOption ? 'div' : 'button';

					return (
						<Option
							key={mode.value}
							{...(isLockedOption
								? { 'aria-disabled': true }
								: { type: 'button', disabled: isDisabled, role: 'radio', 'aria-checked': isActive })}
							className={ClassNames([
								style.option,
								isActive ? style.active : '',
								isLockedOption ? style.locked : ''
							])}
							onClick={() => {
								if (isDisabled) return;
								onChange(mode.value);
							}}
						>
							<span className={style.icon} aria-hidden="true">
								<svg viewBox="0 0 24 24" fill="none">
									{ICONS[mode.value] || null}
								</svg>
							</span>
							<span className={style.text}>
								<span className={style.name}>
									{mode.label}
									{mode.recommended && !isLockedOption ? (
										<span className={style.recommended}>
											{wp.i18n.__('Recommended', 'media-cloud-sync')}
										</span>
									) : null}
									{isLockedOption ? <ProLock /> : null}
								</span>
								{mode.description && !isRow ? (
									<span className={style.hint}>{mode.description}</span>
								) : null}
							</span>
							<span className={style.radio} aria-hidden="true" />
						</Option>
					);
				})}
			</div>
			{isRow && activeMode?.description ? (
				<div className={style.rowHint}>{activeMode.description}</div>
			) : null}
		</div>
	);
}
