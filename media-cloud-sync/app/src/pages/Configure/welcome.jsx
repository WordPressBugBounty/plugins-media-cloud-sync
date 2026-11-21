import React from 'react';
import style from './style.scss';
import getSchema from '../../schema';
import WizardIcon from '../../components/WizardShell/icons';

// Content comes from the schema `welcome` section; the provider list from `services`.
export default function Welcome() {
	const welcome = getSchema('welcome');
	const services = Object.values(getSchema('services') || {});

	return (
		<div className={style.welcomePage}>
			<div className={style.intro}>
				{(welcome?.paragraphs || []).map((text) => (
					<p key={text}>{text}</p>
				))}
				{welcome?.highlights?.length ? (
					<div className={style.pills}>
						{welcome.highlights.map((item) => (
							<span key={item?.text} className={style.pill}>
								<WizardIcon name={item?.icon} size={13} strokeWidth={2} />
								{item?.text}
							</span>
						))}
					</div>
				) : null}
			</div>
			{services.length ? (
				<div className={style.worksWith}>
					<div className={style.eyebrow}>
						<WizardIcon name="cloud" size={14} />
						{welcome?.providersTitle}
					</div>
					<div className={style.providerList}>
						{services.map((service) => (
							<div key={service?.slug} className={style.providerItem}>
								<span className={style.providerLogo}>
									<img src={service?.icon} alt="" />
								</span>
								{service?.label}
							</div>
						))}
					</div>
				</div>
			) : null}
		</div>
	);
}
