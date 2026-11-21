import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';

import style from './style.scss';
import getSchema from '../../schema';
import { ClassNames } from '../../helper';

import { defaultTemporaryActions } from '../../redux/slices/tempCredential';

import CreateBucket from '../../components/CreateBucket';
import ChooseBucket from '../../components/ChooseBucket';
import OptionCard, { OptionGroup } from '../../components/OptionCard';
import WizardIcon from '../../components/WizardShell/icons';

export default function SetBucket({
	showErrors,
	errors,
	listBucketValidateMessage,
	showListBucketValidateMessage,
	slice = 'tempCredentials',
	actions = defaultTemporaryActions
}) {
	const dispatch = useDispatch();
	const services = getSchema('services');
	const defaultValues = getSchema('bucketDefaultValues');
	const bucketAddNewDefaultValues = getSchema('bucketAddNewDefaultValues');
	const service = useSelector((state) => state?.[slice]?.service);
	const currentService = services?.[service];

	const bucketData = useSelector(
		(state) => state?.[slice]?.bucketData
	);

	const {
		config: bucketConfig,
		addNewConfig,
		addNew,
		bucketNameChoose: propBucketNameChoose
	} = bucketData;

	const [createBucket, setCreateBucket] = useState(
		currentService?.storage?.addNew?.enable ? addNew : false
	);

	const [bucketNameChoose, setBucketNameChoose] =
		useState(propBucketNameChoose);

	useEffect(() => {
		// Set default values for bucket config if not already set
		dispatch(
			actions.setTemporaryBucketData({
				config: {
					...defaultValues?.[currentService?.slug],
					...bucketConfig
				},
				addNewConfig: {
					...bucketAddNewDefaultValues?.[currentService?.slug],
					...addNewConfig
				},
				addNew: createBucket,
				bucketNameChoose
			})
		);
	}, [createBucket, bucketNameChoose]);

	const storageLabel = currentService?.storage?.label;

	return (
		<div className={style.setBucketPage}>
			{currentService?.storage?.addNew?.enable && (
				<OptionGroup label={storageLabel}>
					<OptionCard
						icon="database"
						title={`${wp.i18n.__('Use existing', 'media-cloud-sync')} ${storageLabel}`}
						description={wp.i18n.__(
							'Pick one from your account or type its name.',
							'media-cloud-sync'
						)}
						active={!createBucket}
						onSelect={() => setCreateBucket(false)}
					/>
					<OptionCard
						icon="databasePlus"
						title={`${wp.i18n.__('Create new', 'media-cloud-sync')} ${storageLabel}`}
						description={wp.i18n.__(
							'We’ll create it with your credentials.',
							'media-cloud-sync'
						)}
						active={createBucket}
						onSelect={() => setCreateBucket(true)}
					/>
				</OptionGroup>
			)}

			<div className={style.modes}>
				{createBucket && currentService?.storage?.addNew?.enable ? (
					<CreateBucket
						config={currentService?.storage?.addNew}
						showErrors={showErrors}
						errors={errors}
						slice={slice}
						actions={actions}
					/>
				) : (
					<>
						{currentService?.storage?.showManualInput && (
							<>
								<div className={style.segmented} role="radiogroup">
									<button
										type="button"
										role="radio"
										aria-checked={bucketNameChoose}
										className={ClassNames([
											style.segment,
											bucketNameChoose ? style.active : ''
										])}
										onClick={() => setBucketNameChoose(true)}
									>
										<WizardIcon name="list" size={14} strokeWidth={2} />
										{wp.i18n.__('Select from list', 'media-cloud-sync')}
									</button>
									<button
										type="button"
										role="radio"
										aria-checked={!bucketNameChoose}
										className={ClassNames([
											style.segment,
											!bucketNameChoose ? style.active : ''
										])}
										onClick={() => setBucketNameChoose(false)}
									>
										<WizardIcon name="pencil" size={14} strokeWidth={2} />
										{wp.i18n.__('Enter manually', 'media-cloud-sync')}
									</button>
								</div>
								{bucketNameChoose &&
									showListBucketValidateMessage && (
										<div className={style.warning}>
											<WizardIcon name="warn" size={16} />
											{listBucketValidateMessage}
										</div>
									)}
							</>
						)}
						<ChooseBucket
							config={currentService?.storage}
							isTextbox={!bucketNameChoose}
							showErrors={showErrors}
							errors={errors}
							slice={slice}
							actions={actions}
						/>
					</>
				)}
			</div>
		</div>
	);
}
