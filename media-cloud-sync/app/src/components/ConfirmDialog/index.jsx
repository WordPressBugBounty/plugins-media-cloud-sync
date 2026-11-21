import React from 'react';
import style from './style.scss';
import { PrimaryButton, SecondaryButton } from '../Buttons';
import closeIcon from '../../images/icons/close.svg';

export default function ConfirmDialog({
	open,
	title,
	message,
	messageHtml,
	children,
	confirmLabel = wp.i18n.__('Confirm', 'media-cloud-sync'),
	cancelLabel = wp.i18n.__('Cancel', 'media-cloud-sync'),
	danger = false,
	loading = false,
	onConfirm,
	onCancel
}) {
	React.useEffect(() => {
		if (!open) return undefined;
		const onKeyDown = (e) => {
			if (e.key === 'Escape' && !loading) onCancel();
		};
		window.addEventListener('keydown', onKeyDown);
		return () => window.removeEventListener('keydown', onKeyDown);
	}, [open, loading, onCancel]);

	if (!open) {
		return null;
	}

	return (
		<div
			className={style.overlay}
			onMouseDown={(e) => {
				if (e.target === e.currentTarget && !loading) {
					onCancel();
				}
			}}
		>
			<div className={style.dialog}>
				<button
					type="button"
					className={style.close}
					onClick={onCancel}
					disabled={loading}
					aria-label={wp.i18n.__('Close', 'media-cloud-sync')}
				>
					<img src={closeIcon} alt="" />
				</button>
				{title ? <h3>{title}</h3> : null}
				{message ? <p>{message}</p> : null}
				{messageHtml ? (
					// eslint-disable-next-line react/no-danger
					<div dangerouslySetInnerHTML={{ __html: messageHtml }} />
				) : null}
				{children}
				<div className={style.actions}>
					<SecondaryButton
						type="button"
						label={cancelLabel}
						onClick={onCancel}
						disabled={loading}
					/>
					{danger ? (
						<SecondaryButton
							type="button"
							variant="danger"
							label={confirmLabel}
							loading={loading}
							disabled={loading}
							onClick={onConfirm}
						/>
					) : (
						<PrimaryButton
							type="button"
							label={confirmLabel}
							loading={loading}
							disabled={loading}
							onClick={onConfirm}
						/>
					)}
				</div>
			</div>
		</div>
	);
}
