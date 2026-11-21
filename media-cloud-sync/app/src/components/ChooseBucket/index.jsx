import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import style from './style.scss';

import ToggleButton from '../ToggleButton';
import SelectInput from '../SelectInput';
import TextInput from '../TextInput';
import { defaultTemporaryActions } from '../../redux/slices/tempCredential';

export default function ChooseBucket({
	config,
	isTextbox,
	showErrors,
	errors,
	slice = 'tempCredentials',
	actions = defaultTemporaryActions
}) {
	const dispatch = useDispatch();
	const buckets = useSelector((state) => state?.[slice]?.buckets);
	const bucketConfig = useSelector(
		(state) => state?.[slice]?.bucketData?.config
	);

	const handleChange = (key, value) => {
		dispatch(
			actions.setTemporaryBucketConfig({
				...bucketConfig,
				[key]: value
			})
		);
	};

	const fieldOptions = {
		bucket_name: buckets?.map((buck) => ({
			label: buck?.Name,
			value: buck?.Name
		}))
	};

	return (
		<div className={style.chooseBucket}>
			{config?.fields?.map((field) => {
				const fieldType =
					isTextbox && field?.isTextBoxType?.length
						? field?.isTextBoxType
						: field?.type;
				const error = errors?.find((err) => err.name === field?.name);
				switch (fieldType) {
					case 'toggle':
						return (
							<ToggleButton
								label={field?.label}
								icon={field?.icon}
								name={field?.name}
								key={field?.name}
								value={bucketConfig?.[field?.name]}
								onChange={handleChange}
							/>
						);
					case 'select':
						return (
							<SelectInput
								type="text"
								label={field?.label}
								icon={field?.icon}
								name={field?.name}
								key={field?.name}
								options={fieldOptions?.[field?.name]}
								placeholder={field.placeholder}
								value={bucketConfig?.[field?.name]}
								error={showErrors && error}
								errorMessage={error?.message}
								onChange={handleChange}
							/>
						);
					default:
						return (
							<TextInput
								type="text"
								label={field?.label}
								icon={field?.icon}
								name={field?.name}
								key={field?.name}
								placeholder={field.placeholder}
								value={bucketConfig?.[field?.name]}
								error={showErrors && error}
								errorMessage={error?.message}
								onChange={handleChange}
							/>
						);
				}
			})}
		</div>
	);
}
