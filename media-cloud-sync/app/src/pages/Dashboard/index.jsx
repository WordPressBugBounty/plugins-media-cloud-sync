import React from 'react';
import { useSelector } from 'react-redux';
import ConnectionCard from '../../components/ConnectionCard';
import PremiumBanner from '../../components/PremiumBanner';
import PremiumCard from '../../components/SyncScreen/PremiumCard';
import WelcomeContent from '../../components/WelcomeContent';
import LibrarySync from '../../components/LibrarySync';
import ToolList from '../../components/ToolList';
import getSchema from '../../schema';
import style from './style.scss';
import { isPro, isCardDisabled } from '../../helper';

export default function Dashboard() {
	const cards = [
		...(getSchema('dashboard_cards') || []),
		...(getSchema('dashboard_pro_cards') || [])
	];
	const savedCredentials = useSelector((state) => state?.credentials);
	const { service, serviceEnabled } = savedCredentials;
	const cloudOperational =
		!!service && serviceEnabled !== false;

	// `group: links` cards go to the sidebar; the rest are bulk tools.
	const withState = (card) => ({
		...card,
		disabled: isCardDisabled(card, service, cloudOperational)
	});
	const tools = cards.filter((card) => card?.group !== 'links').map(withState);
	// `last` links (e.g. Import / Export) go after add-on links; external ones (Documentation) at the end.
	const linkRank = (card) => (card?.external ? 2 : card?.last ? 1 : 0);
	const links = cards
		.filter((card) => card?.group === 'links')
		.sort((a, b) => linkRank(a) - linkRank(b))
		.map(withState);

	return (
		<div className={style.dashboardInner}>
			<WelcomeContent />
			<div className={style.dashLayout}>
				<div className={style.dashMain}>
					<LibrarySync />
					<ToolList
						title={
							getSchema('dashboard')?.toolsTitle ||
							wp.i18n.__('Bulk tools', 'media-cloud-sync')
						}
						items={tools}
					/>
				</div>
				<aside className={style.dashSide}>
					<ConnectionCard />
					<ToolList items={links} compact />
					{!isPro() ? <PremiumCard className={style.sidePremium} /> : null}
				</aside>
			</div>
			{!isPro() ? <PremiumBanner /> : null}
		</div>
	);
}
