import React, { useEffect, useRef, useState } from 'react';
import { useSelector } from 'react-redux';
import { Link, useLocation } from 'react-router-dom';
import { PremiumButton } from '../Buttons';

import style from './style.scss';
import logo from '../../images/logo.svg';
import HeaderProgressIndicator from '../HeaderPrgressIndicator';
import { isPro } from '../../helper';
import getSchema from '../../schema';
import Slot from '../Slot';

// Stroke paths for the schema nav `icon` names.
const NAV_ICONS = {
	dashboard: (
		<>
			<rect x="4" y="4" width="7" height="7" rx="1.5" />
			<rect x="13" y="4" width="7" height="7" rx="1.5" />
			<rect x="4" y="13" width="7" height="7" rx="1.5" />
			<rect x="13" y="13" width="7" height="7" rx="1.5" />
		</>
	),
	settings: (
		<>
			<circle cx="12" cy="12" r="3" />
			<path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8" />
		</>
	),
	tools: (
		<path d="M14.5 6.5a4 4 0 0 0-5.3 5.3L4 17l3 3 5.2-5.2a4 4 0 0 0 5.3-5.3l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6Z" />
	),
	license: (
		<>
			<circle cx="8" cy="15" r="4" />
			<path d="M11 12l8-8M16 7l2.5 2.5M18 5l2 2" />
		</>
	),
	docs: (
		<path d="M5 5.5A1.5 1.5 0 0 1 6.5 4H19v14H6.5A1.5 1.5 0 0 0 5 19.5v-14ZM5 19.5A1.5 1.5 0 0 0 6.5 21H19" />
	)
};

function NavIcon({ name }) {
	if (!NAV_ICONS[name]) {
		return null;
	}
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.7"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
		>
			{NAV_ICONS[name]}
		</svg>
	);
}

export default function Header({ loader }) {
	const location = useLocation();
	const { version } = useSelector((state) => state?.common);
	const savedCredentials = useSelector((state) => state?.credentials);
	const { service } = savedCredentials;

	const proActive = isPro();

	const upsell = getSchema('upsell');

	// Nav comes from the schema.
	const visibleMenu = getSchema('nav').filter(
		(item) => !(item?.requires === 'service' && !service)
	);

	const isActive = (path) => location.pathname === path;

	// Phones: the menu scrolls sideways; fade the edges that have more items.
	const navRef = useRef(null);
	const [edges, setEdges] = useState({ start: false, end: false });
	const updateEdges = () => {
		const nav = navRef.current;
		if (!nav) return;
		setEdges({
			start: nav.scrollLeft > 2,
			end: nav.scrollLeft + nav.clientWidth < nav.scrollWidth - 2
		});
	};
	useEffect(() => {
		const nav = navRef.current;
		nav?.querySelector(`.${style.active}`)?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
		updateEdges();
		window.addEventListener('resize', updateEdges);
		return () => window.removeEventListener('resize', updateEdges);
	}, [location.pathname, loader]);
	return (
		<div className={style.header}>
			<div className={style.top}>
				<div className={style.brand}>
					<img src={logo} alt="" />
					<div className={style.wordmark}>
						<strong>{wp.i18n.__('Media Cloud Sync', 'media-cloud-sync')}</strong>
						<span>
							{wp.i18n.__('by', 'media-cloud-sync')}{' '}
							<a
								href="https://dudlewebs.com/"
								target="_blank"
								rel="noreferrer"
							>
								Dudlewebs
							</a>
						</span>
					</div>
				</div>
				<div className={style.right}>
					{version ? <span className={style.version}>v{version}</span> : null}
					<Slot name="header.meta" className={`${style.version} ${style.proVersion}`} />
					{!proActive && upsell?.headerButton ? (
						<PremiumButton
							label={upsell.headerButton}
							link={upsell.url}
							target="_blank"
						/>
					) : null}
				</div>
			</div>

			{!loader ? (
				<div className={style.bottom}>
					<nav
						ref={navRef}
						onScroll={updateEdges}
						className={[
							style.nav,
							edges.start ? style.fadeStart : '',
							edges.end ? style.fadeEnd : ''
						].join(' ')}
					>
						{visibleMenu.map((item) =>
							item?.external ? (
								<a
									key={item?.label}
									href={item?.path}
									target="_blank"
									rel="noreferrer"
								>
									<NavIcon name={item?.icon} />
									{item?.label}
									<svg
										className={style.external}
										width="11"
										height="11"
										viewBox="0 0 24 24"
										fill="none"
										aria-hidden="true"
									>
										<path
											d="M9 5h10v10M19 5 6 18"
											stroke="currentColor"
											strokeWidth="2.2"
											strokeLinecap="round"
											strokeLinejoin="round"
										/>
									</svg>
								</a>
							) : (
								<Link
									key={item?.path}
									to={item?.path}
									className={
										isActive(item?.path)
											? style.active
											: null
									}
									aria-current={isActive(item?.path) ? 'page' : undefined}
								>
									<NavIcon name={item?.icon} />
									{item?.label}
								</Link>
							)
						)}
					</nav>
					<HeaderProgressIndicator />
				</div>
			) : null}
		</div>
	);
}
