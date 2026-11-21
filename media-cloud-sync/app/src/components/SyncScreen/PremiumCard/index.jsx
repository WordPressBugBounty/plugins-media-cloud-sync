import React from 'react';

import style from './style.scss';
import getSchema from '../../../schema';
import { ClassNames } from '../../../helper';

// Content comes from the schema `upsell.card` section.
export default function PremiumCard({ className = null }) {
	const upsell = getSchema('upsell');
	const card = upsell?.card;

	if (!card) {
		return null;
	}

	return (
		<aside className={ClassNames([style.premiumCard, className])}>
			<div className={style.head}>
				<span className={style.icon} aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none">
						<path
							d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8Z"
							stroke="currentColor"
							strokeWidth="1.7"
							strokeLinejoin="round"
						/>
					</svg>
				</span>
				{upsell.lockedBadge ? (
					<span className={style.badge}>{upsell.lockedBadge}</span>
				) : null}
			</div>
			<h2>{card.title}</h2>
			{card.text ? <p className={style.text}>{card.text}</p> : null}
			<ul>
				{(card.features || []).map((feature) => (
					<li key={feature}>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
							<path
								d="M5 12.5l4.5 4.5L19 7.5"
								stroke="currentColor"
								strokeWidth="2.4"
								strokeLinecap="round"
								strokeLinejoin="round"
							/>
						</svg>
						<span>{feature}</span>
					</li>
				))}
			</ul>
			<a
				href={upsell.url}
				target="_blank"
				rel="noreferrer"
				className={style.button}
			>
				{card.button}
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
					<path
						d="M5 12h14m-5-5 5 5-5 5"
						stroke="currentColor"
						strokeWidth="2"
						strokeLinecap="round"
						strokeLinejoin="round"
					/>
				</svg>
			</a>
		</aside>
	);
}
