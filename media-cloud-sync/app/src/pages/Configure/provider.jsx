import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';

import style from './style.scss';
import getSchema from '../../schema';
import {
	setTemporaryData,
	setTemporaryServiceLabel
} from '../../redux/slices/tempCredential';
import { ClassNames } from '../../helper';
import TextInput from '../../components/TextInput';
import WizardIcon from '../../components/WizardShell/icons';

export default function Provider({ setConfig, errors, showErrors }) {
	const dispatch = useDispatch();
	const services = getSchema('services');
	const savedData = useSelector((state) => state?.credentials);
	const {
		service: curService,
		serviceLabel: curServiceLabel,
		cdn: curCdn,
		config: curConfig,
		bucketConfig: curBucketConfig,
		security: curSecurity
	} = savedData;
	const tempData = useSelector((state) => state?.tempCredentials);
	const { service: tempService, serviceLabel: tempServiceLabel } = tempData;

	const choose = (service) => {
		const currentConfig =
			curService && curService === service
				? {
						...savedData,
						service: curService,
						cdn: curCdn,
						serviceLabel: curServiceLabel,
						config: curConfig,
						bucketData: {
							config: curBucketConfig,
							addNewConfig: {},
							addNew: false,
							bucketNameChoose: true
						},
						security: curSecurity
				  }
				: {
						...tempData,
						service,
						serviceLabel: '',
						// No delivery provider until the user picks one on the delivery screen.
						cdn: { service: '', label: '' },
						config: {},
						bucketData: {
							config: {},
							addNewConfig: {},
							addNew: false,
							bucketNameChoose: true
						},
						security: {}
				  };

		setConfig((prev) => ({
			...prev,
			disabled: {
				next:
					!services?.[service]?.mainLabel?.length &&
					!tempServiceLabel,
				back: false
			}
		}));

		dispatch(setTemporaryData(currentConfig));
	};

	const handleChangeServiceLabel = (name, value) => {
		dispatch(setTemporaryServiceLabel(value));
	};

	useEffect(() => {
		setConfig((prev) => ({
			...prev,
			disabled: {
				next:
					!services?.[tempService]?.mainLabel?.length &&
					!tempServiceLabel,
				back: false
			}
		}));
	}, [tempServiceLabel]);

	useEffect(() => {
		if (curService?.length) {
			choose(curService);
		}
	}, [curService]);

	const choosedService =
		services?.[useSelector((state) => state?.tempCredentials?.service)];

	return (
		<div className={style.providerPage}>
			<div className={style.innerSection}>
				<div className={style.tileGrid} role="radiogroup">
					{Object.keys(services).map((service) => (
						<div
							className={ClassNames([
								style.tile,
								service === tempService ? style.active : null
							])}
							key={service}
							onClick={() => choose(service)}
							role="radio"
							aria-checked={service === tempService}
							onKeyDown={(event) => {
								if (event.key === 'Enter' || event.key === ' ') {
									event.preventDefault();
									choose(service);
								}
							}}
							tabIndex={0}
						>
							{service === tempService ? (
								<span className={style.tick}>
									<WizardIcon name="check" size={12} strokeWidth={3} />
								</span>
							) : null}
							<span className={style.tileLogo}>
								<img src={services?.[service].icon} alt="" />
							</span>
							<strong>{services?.[service].label}</strong>
							<span className={style.tileDesc}>
								{services?.[service].description}
							</span>
						</div>
					))}
				</div>
				<div className={style.contentSec}>
					{choosedService && !choosedService?.mainLabel?.length && (
						<div className={style.formControl}>
							<TextInput
								name="serviceLabel"
								icon="pencil"
								value={tempServiceLabel}
								onChange={handleChangeServiceLabel}
								type="text"
								error={
									showErrors &&
									errors?.find(
										(err) => err.name === 'serviceLabel'
									)
								}
								errorMessage={
									errors?.find(
										(err) => err.name === 'serviceLabel'
									)?.message
								}
								label={wp.i18n.__(
									'Enter Provider label',
									'media-cloud-sync'
								)}
								placeholder={wp.i18n.__(
									'Enter label',
									'media-cloud-sync'
								)}
								required
							/>
						</div>
					)}
				</div>
			</div>
		</div>
	);
}
