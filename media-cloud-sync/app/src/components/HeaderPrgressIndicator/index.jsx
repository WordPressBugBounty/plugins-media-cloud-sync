import React from 'react';
import { useSelector } from 'react-redux';
import { Link } from 'react-router-dom';

import style from './style.scss';
import { ClassNames, formatPercent, jobPercentage } from '../../helper';
import getSchema from '../../schema';

// Chip for whichever job is running or paused; links to its screen.
export default function HeaderProgressIndicator() {
	const status = useSelector((state) => state?.sync);
	const counts = useSelector((state) => state?.common?.counts);
	const syncScreen = getSchema('syncScreen');

	const [running, setRunning] = React.useState('stopped');
	const [current, setCurrent] = React.useState(null);

	React.useEffect(() => {
		if (status) {
			let isRunning = false;
			Object.keys(status).forEach((key) => {
				if (
					status[key]?.status === 'running' ||
					status[key]?.status === 'paused'
				) {
					setRunning(status[key]?.status);
					setCurrent(key);
					isRunning = true;
				}
			});

			if (!isRunning) {
				setRunning('stopped');
				setCurrent(null);
			}
		}
	}, [status]);

	if (running === 'stopped') {
		return null;
	}

	const screen = syncScreen?.[current];
	const percentage = formatPercent(jobPercentage(screen, status?.[current], counts));
	const title = screen?.title || wp.i18n.__('Job in progress', 'media-cloud-sync');
	const paused = running === 'paused';

	return (
		<Link
			to={screen?.path || '/'}
			className={ClassNames([style.chip, paused ? style.paused : null])}
			title={wp.i18n.__('View details', 'media-cloud-sync')}
		>
			<span className={style.dot} aria-hidden="true" />
			{paused ? (
				<span className={style.state}>
					{wp.i18n.__('Paused', 'media-cloud-sync')}
				</span>
			) : null}
			<span className={style.title}>{title}</span>
			<span className={style.percent}>{percentage}%</span>
		</Link>
	);
}
