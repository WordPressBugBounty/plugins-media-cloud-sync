import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { toast } from 'react-toastify';

import style from './style.scss';
import getSchema from '../../schema';
import SelectInput from '../../components/SelectInput';
import { setTemporarySecurity } from '../../redux/slices/tempCredential';
import ToggleButton from '../../components/ToggleButton';
import TextInput from '../../components/TextInput';
import WizardIcon from '../../components/WizardShell/icons';
import { ClassNames, fetchWP } from '../../helper';

export default function SetSecurity({ setConfig }) {
	const dispatch = useDispatch();
	const services = getSchema('services');
	const service = useSelector((state) => state?.tempCredentials?.service);
	const tempConfig = useSelector((state) => state?.tempCredentials?.config);
	const configSource = useSelector(
		(state) => state?.tempCredentials?.configSource
	);
	const bucketData = useSelector(
		(state) => state?.tempCredentials?.bucketData
	);
	const config = useSelector((state) => state?.tempCredentials?.security);
	const currentService = services?.[service];

	const [loading, setLoading] = useState(true);
	const [errors, setError] = useState({});

	const { security } = currentService;

	useEffect(() => {
		setConfig((prev) => ({
			...prev,
			disabled: {
				next: true,
				back: true
			}
		}));

		fetchWP
			.post(`security/get_security`, {
				service,
				configSource,
				config: configSource === 'config' ? {} : tempConfig,
				bucketData
			})
			.then((response) => {
				if (response.success) {
					dispatch(
						setTemporarySecurity({
							...config,
							...response.security
						})
					);
					setError({});
				} else {
					setError(response?.errors || []);
				}
				setConfig((prev) => ({
					...prev,
					disabled: {
						next: false,
						back: false
					}
				}));
				setLoading(false);
			})
			.catch((er) => {
				setLoading(false);
				setConfig((prev) => ({
					...prev,
					disabled: {
						next: false,
						back: false
					}
				}));
				toast.error(er.message, {
					autoClose: 4000
				});
			});
	}, []);

	/**
	 * Handles the change event for the security form fields.
	 *
	 * Upon changing a field, the function will post the updated data to the
	 * security endpoint for the respective field. If the response is successful,
	 * the tempConfig state will be updated with the new value. The disabled state
	 * of the next and back buttons will be toggled accordingly.
	 *
	 * @param {string} key The name of the field being changed.
	 * @param {string} value The new value of the field being changed.
	 */
	const handleChange = (key, value) => {
		const endpoint = security?.fields?.find(
			(field) => field?.name === key
		)?.endpoint;

		setConfig((prev) => ({
			...prev,
			disabled: {
				next: true,
				back: true
			}
		}));

		setLoading(true);

		fetchWP
			.post(`security/${endpoint}`, {
				service,
				configSource,
				config: configSource === 'config' ? {} : tempConfig,
				bucketData,
				value
			})
			.then((response) => {
				if (response.success) {
					dispatch(
						setTemporarySecurity({
							...config,
							[key]: value
						})
					);
					
					setConfig((prev) => ({
						...prev,
						disabled: {
							next: false,
							back: false
						}
					}));
				} else {
					setConfig((prev) => ({
						...prev,
						disabled: {
							next: true,
							back: false
						}
					}));
					toast.error(response.message, {
						autoClose: 4000
					});
				}
				setLoading(false);
			})
			.catch((er) => {
				setLoading(false);
				setConfig((prev) => ({
					...prev,
					disabled: {
						next: true,
						back: false
					}
				}));
				toast.error(er.message, {
					autoClose: 4000
				});
			});
	};

	return (
		<div className={style.securityPage} aria-busy={loading}>
			{loading ? (
				<div className={style.loader}>
					<span className={style.spinner} />
				</div>
			) : null}
			<div className={style.fields}>
				{security?.fields?.map((field) => (
					<div
						className={ClassNames([
							style.securityBlock,
							field?.type === 'toggle' ? style.toggleRow : ''
						])}
						key={field?.name}
					>
						{field?.type === 'toggle' ? (
							<>
								<span className={style.rowIcon}>
									<WizardIcon name={field?.icon || 'shield'} size={18} />
								</span>
								<div className={style.rowText}>
									<strong>{field?.label}</strong>
									{field?.desc ? <span>{field.desc}</span> : null}
									{errors?.[field?.name]?.length ? (
										<span className={style.rowError}>
											{errors[field.name]}
										</span>
									) : null}
								</div>
								<ToggleButton
									label=""
									name={field?.name}
									value={config?.[field?.name]}
									onChange={handleChange}
									disabled={!!errors?.[field?.name]?.length || loading}
								/>
							</>
						) : null}
						{field?.type !== 'toggle' && (() => {
							switch (field?.type) {
								case 'select':
									return (
										<SelectInput
											type="text"
											label={field?.label}
											name={field?.name}
											options={field?.options}
											placeholder={field?.placeholder}
											value={config?.[field?.name]}
											onChange={handleChange}
											disabled={
												!!errors?.[field?.name]
													?.length || loading
											}
											error={
												!!errors?.[field?.name]?.length
											}
											errorMessage={errors?.[field?.name]}
										/>
									);
								default:
									return (
										<TextInput
											type="text"
											label={field?.label}
											name={field?.name}
											placeholder={field?.placeholder}
											value={config?.[field?.name]}
											onChange={handleChange}
											disabled={loading}
										/>
									);
							}
						})()}
						{field?.type !== 'toggle' && field?.desc ? (
							<div className={style.securityBlockContent}>
								{field.desc}
							</div>
						) : null}
					</div>
				))}
			</div>
		</div>
	);
}
