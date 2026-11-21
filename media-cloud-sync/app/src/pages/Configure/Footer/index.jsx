import React from 'react';
import { PrimaryButton } from '../../../components/Buttons';
import WizardIcon from '../../../components/WizardShell/icons';
import style from './style.scss';

export default function Footer({
	onBack,
	onNext,
	disabled = {
		next: false,
		back: false
	},
	labels = {
		back: wp.i18n.__('Back', 'media-cloud-sync'),
		next: wp.i18n.__('Next', 'media-cloud-sync')
	},
	display = { back: true, next: true },
	loading = false,
	hint = ''
}) {
	// `labels.icon` (schema) leads the Next label; without it an arrow trails.
	const nextLabel = (
		<>
			{labels?.icon ? <WizardIcon name={labels.icon} size={16} strokeWidth={2} /> : null}
			{labels?.next}
			{labels?.icon ? null : <WizardIcon name="right" size={14} strokeWidth={2.2} />}
		</>
	);

	return (
		<div className={style.footer}>
			{display?.back ? (
				<button
					type="button"
					className={style.backButton}
					onClick={onBack}
					disabled={disabled?.back}
				>
					<WizardIcon name="left" size={14} strokeWidth={2.2} />
					{labels?.back}
				</button>
			) : null}
			{hint ? (
				<span className={style.hint}>
					<WizardIcon name="warn" size={14} />
					{hint}
				</span>
			) : null}
			{display?.next ? (
				<div className={style.next}>
					<PrimaryButton
						label={nextLabel}
						onClick={onNext}
						type="button"
						disabled={disabled?.next}
						loading={loading}
						className={style.button}
					/>
				</div>
			) : null}
		</div>
	);
}
