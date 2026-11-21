import React from 'react';
import { Link } from 'react-router-dom';
import style from './style.scss';
import getSchema from '../../schema';
import { ClassNames, getProUnlockCta } from '../../helper';

// PRO badge on a PRO preview; hover/focus shows why plus an Upgrade (or add-on supplied) link.
// `align="left"` opens the popover to the right of the badge (e.g. inline after a label).
export default function ProLock({ className = null, align = 'right' }) {
	const upsell = getSchema('upsell');
	const cta = getProUnlockCta();

	return (
		<span
			className={ClassNames([
				style.proLock,
				align === 'left' ? style.alignLeft : null,
				className
			])}
			tabIndex={0}
			onClick={(event) => event.stopPropagation()}
		>
			<span className={style.badge}>{upsell?.lockedBadge}</span>
			<span className={style.popover} role="tooltip">
				<span className={style.panel}>
					<span className={style.message}>{cta.message}</span>
					{cta.to ? (
						<Link to={cta.to} className={style.cta}>
							{cta.label}
						</Link>
					) : (
						<a
							href={cta.href}
							target="_blank"
							rel="noreferrer"
							className={style.cta}
						>
							{cta.label}
						</a>
					)}
				</span>
			</span>
		</span>
	);
}
