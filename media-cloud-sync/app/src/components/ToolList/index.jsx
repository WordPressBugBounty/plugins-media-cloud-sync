import React from 'react';
import { Link } from 'react-router-dom';
import ProLock from '../ProLock';
import { ClassNames } from '../../helper';
import style from './style.scss';

// Rows of schema cards (bulk tools, or quick links when `compact`).
export default function ToolList({ title = '', items = [], compact = false }) {
	if (!items.length) {
		return null;
	}

	const row = (item) => {
		const body = (
			<>
				<span
					className={style.icon}
					style={{ backgroundColor: item?.iconBgColor }}
				>
					<img src={item?.icon} alt="" />
				</span>
				<span className={style.text}>
					<strong>{item?.label}</strong>
					{!compact && item?.description ? (
						<span>{item.description}</span>
					) : null}
				</span>
			</>
		);

		if (item?.preview) {
			return (
				<div key={item.slug} className={ClassNames([style.row, style.locked])}>
					{body}
					<ProLock className={style.lock} />
				</div>
			);
		}

		if (item?.disabled) {
			return (
				<div
					key={item.slug}
					className={ClassNames([style.row, style.disabled])}
					aria-disabled="true"
				>
					{body}
				</div>
			);
		}

		const arrow = (
			<span className={style.arrow} aria-hidden="true">
				{item?.external ? '↗' : '›'}
			</span>
		);

		return item?.external ? (
			<a
				key={item.slug}
				href={item?.link}
				target="_blank"
				rel="noreferrer"
				className={style.row}
			>
				{body}
				{arrow}
			</a>
		) : (
			<Link key={item.slug} to={item?.link} className={style.row}>
				{body}
				{arrow}
			</Link>
		);
	};

	return (
		<section className={ClassNames([style.list, compact ? style.compact : null])}>
			{title ? <h2>{title}</h2> : null}
			{items.map(row)}
		</section>
	);
}
