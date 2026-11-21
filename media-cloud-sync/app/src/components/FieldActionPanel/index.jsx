import React from 'react';
import { useDispatch } from 'react-redux';
import { toast } from 'react-toastify';
import { ClassNames, getApiClient } from '../../helper';
import { setStatus } from '../../redux/slices/common';
import { PrimaryButton, SecondaryButton } from '../Buttons';
import style from './style.scss';

/**
 * Apply/Remove panel under a settings field, driven by the field's schema `action`:
 * { title, statusKey, endpoint, api: 'pro'|'free', watch: [setting keys], notes: { service: text },
 *   labels: { applied, notApplied, appliedMessage, notAppliedMessage, apply, remove, unsavedHint, applyFailed, removeFailed } }.
 * The endpoint receives { remove } and may return { success, message, policy_status }.
 */
export default function FieldActionPanel({
	action,
	status,
	service,
	savedSettings,
	draftSettings
}) {
	const dispatch = useDispatch();
	const [loading, setLoading] = React.useState(null);
	const labels = action?.labels || {};
	const applied = !!status?.[action?.statusKey]?.status;
	// Actions run against saved settings, never an unsaved draft.
	const hasUnsaved = (action?.watch || []).some(
		(key) => draftSettings?.[key] !== savedSettings?.[key]
	);
	const note = action?.notes?.[service];

	const run = (remove) => {
		setLoading(remove ? 'remove' : 'apply');
		getApiClient(action?.api)
			.post(action.endpoint, { remove })
			.then((res) => {
				if (res?.success) {
					toast.success(res.message, { autoClose: 3000 });
				} else {
					toast.error(res?.message, { autoClose: 3000 });
				}
				if (res?.policy_status) {
					dispatch(
						setStatus({ key: action.statusKey, value: res.policy_status })
					);
				}
			})
			.catch(() => {
				toast.error(remove ? labels.removeFailed : labels.applyFailed, {
					autoClose: 3000
				});
			})
			.finally(() => setLoading(null));
	};

	return (
		<div className={style.panel}>
			<div className={style.head}>
				<strong>{action?.title}</strong>
				<span
					className={ClassNames([
						style.statusBadge,
						applied && style.applied
					])}
				>
					<span className={style.dot} />
					{applied ? labels.applied : labels.notApplied}
				</span>
			</div>
			<p className={style.message}>
				{applied ? labels.appliedMessage : labels.notAppliedMessage}
			</p>
			{note ? (
				<p className={style.note}>{note}</p>
			) : null}
			<div className={style.actions}>
				{applied ? (
					<SecondaryButton
						label={labels.remove}
						variant="danger"
						loading={loading === 'remove'}
						disabled={!!loading}
						type="button"
						onClick={() => run(true)}
					/>
				) : (
					<PrimaryButton
						size="small"
						label={labels.apply}
						loading={loading === 'apply'}
						disabled={!!loading || hasUnsaved}
						type="button"
						onClick={() => run(false)}
					/>
				)}
				{!applied && hasUnsaved && labels.unsavedHint ? (
					<span className={style.hint}>{labels.unsavedHint}</span>
				) : null}
			</div>
		</div>
	);
}
