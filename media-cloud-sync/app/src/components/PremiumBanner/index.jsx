import React from 'react';
import style from './style.scss';
import getSchema from '../../schema';

// Full-width upgrade banner; content comes from the schema `upsell.banner` section.
export default function PremiumBanner() {
	const upsell = getSchema('upsell');
	const banner = upsell?.banner;

	if (!banner) {
		return null;
	}

	return (
		<section className={style.premium}>
			<div className={style.body}>
				{banner.badge ? (
					<span className={style.badge}>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
							<path
								d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8Z"
								stroke="currentColor"
								strokeWidth="2"
								strokeLinejoin="round"
							/>
						</svg>
						{banner.badge}
					</span>
				) : null}
				<h2>{banner.title}</h2>
				{banner.text ? <p>{banner.text}</p> : null}
				{banner.features?.length ? (
					<ul className={style.chips}>
						{banner.features.map((feature) => (
							<li key={feature}>{feature}</li>
						))}
					</ul>
				) : null}
			</div>
			<div className={style.actions}>
				<a
					href={upsell.url}
					target="_blank"
					rel="noreferrer"
					className={style.button}
				>
					{banner.button}
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
				{banner.link ? (
					<a
						href={upsell.url}
						target="_blank"
						rel="noreferrer"
						className={style.link}
					>
						{banner.link}
					</a>
				) : null}
			</div>
		</section>
	);
}
