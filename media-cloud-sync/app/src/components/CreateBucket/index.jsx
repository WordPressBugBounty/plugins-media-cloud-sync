import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import style from './style.scss';

import ToggleButton from '../ToggleButton';
import SelectInput from '../SelectInput';
import TextInput from '../TextInput';
import { defaultTemporaryActions } from '../../redux/slices/tempCredential';

export default function CreateBucketPopup({
	config,
	errors,
	showErrors,
	slice = 'tempCredentials',
	actions = defaultTemporaryActions
}) {
	const dispatch = useDispatch();
	const addNewConfig = useSelector(
		(state) => state?.[slice]?.bucketData?.addNewConfig
	);

	const handleChange = (key, value) => {
		dispatch(
			actions.setTemporaryAddNewBucketConfig({
				...addNewConfig,
				[key]: value
			})
		);
	};

	return (
		<div className={style.createBucket}>
			{config?.fields?.map((field) => {
				const error = errors?.find((err) => err.name === field?.name);
				switch (field?.type) {
					case 'toggle':
						return (
							<ToggleButton
								label={field?.label}
								icon={field?.icon}
								name={field?.name}
								key={field?.name}
								value={addNewConfig?.[field?.name]}
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
								options={field?.options}
								placeholder={field.placeholder}
								value={addNewConfig?.[field?.name]}
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
								value={addNewConfig?.[field?.name]}
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
