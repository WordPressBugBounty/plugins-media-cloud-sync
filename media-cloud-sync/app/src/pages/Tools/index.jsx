import React from 'react';
import { useSelector } from 'react-redux';
import { Link } from 'react-router-dom';
import getSchema from '../../schema';
import { ClassNames, isCardDisabled } from '../../helper';
import style from './style.scss';
import BackLink from '../../components/BackLink';

// Lists the schema's `tools` cards (provided by PRO).
export default function Tools() {
	const tools = getSchema('tools') || [];
	const page = getSchema('toolsPage') || {};
	const { service, serviceEnabled } = useSelector((state) => state?.credentials);
	const cloudOperational = !!service && serviceEnabled !== false;

	const body = (card) => (
		<>
			<span className={style.toolIcon} style={{ backgroundColor: card?.iconBgColor }}>
				<img src={card?.icon} alt="" />
			</span>
			<span className={style.toolText}>
				<strong>{card?.label}</strong>
				{card?.description ? <span>{card.description}</span> : null}
			</span>
		</>
	);

	return (
		<div className={style.toolsPage}>
			<BackLink />
			<div className={style.toolsHead}>
				<h2>{page.title || wp.i18n.__('Tools', 'media-cloud-sync')}</h2>
				{page.description ? <p>{page.description}</p> : null}
			</div>
			<div className={style.toolGrid}>
				{tools.map((card) =>
					isCardDisabled(card, service, cloudOperational) ? (
						<div
							key={card.slug}
							className={ClassNames([style.tool, style.toolDisabled])}
							aria-disabled="true"
						>
							{body(card)}
							<span className={style.toolNote}>{page.unavailable}</span>
						</div>
					) : (
						<Link key={card.slug} to={card.link} className={style.tool}>
							{body(card)}
							<span className={style.toolOpen}>
								{page.open || wp.i18n.__('Open tool', 'media-cloud-sync')}
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<path
										d="M5 12h14m-5-5 5 5-5 5"
										stroke="currentColor"
										strokeWidth="2"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</svg>
							</span>
						</Link>
					)
				)}
			</div>
		</div>
	);
}
