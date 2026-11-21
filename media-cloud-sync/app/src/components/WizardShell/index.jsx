import React from 'react';
import style from './style.scss';
import WizardIcon from './icons';
import BackLink from '../BackLink';
import getSchema from '../../schema';
import { ClassNames } from '../../helper';

// Setup wizard frame: step rail on the left, step header, body and footer on the right.
// Without `steps` it renders the right-hand card alone (e.g. delivery opened from Settings).
export default function WizardShell({
	steps = [],
	current = '',
	subOrder = [],
	currentSub = '',
	values = {},
	exit = null,
	logo = null,
	title = '',
	desc = '',
	help = null,
	footer = null,
	children
}) {
	const wizard = getSchema('wizard') || {};
	const currentIndex = steps.findIndex((step) => step?.slug === current);
	const hasRail = steps.length > 0;
	const stepOf = (wizard?.stepOf || '')
		.replace('%1$s', currentIndex + 1)
		.replace('%2$s', steps.length);

	const renderSubSteps = (step) => {
		const subs = subOrder.filter((slug) => step?.subSteps?.[slug]);
		if (step?.slug !== current || !subs.length) return null;
		const subIndex = subs.indexOf(currentSub);
		return (
			<ul className={style.subSteps}>
				{subs.map((slug, index) => (
					<li
						key={slug}
						className={ClassNames([
							index < subIndex ? style.subDone : '',
							index === subIndex ? style.subCurrent : ''
						])}
					>
						<WizardIcon
							name={index < subIndex ? 'check' : step.subSteps[slug]?.icon}
							size={14}
							strokeWidth={index < subIndex ? 2.6 : 1.8}
						/>
						{step.subSteps[slug]?.label}
					</li>
				))}
			</ul>
		);
	};

	return (
		<div className={style.wizardPage}>
			{exit ? <BackLink to={exit.to} label={exit.label} /> : null}
			<div className={ClassNames([style.wizard, hasRail ? style.withRail : ''])}>
				{hasRail ? (
					<aside className={style.rail}>
						<div className={style.railHead}>{stepOf}</div>
						<ol className={style.steps}>
							{steps.map((step, index) => {
								const done = index < currentIndex;
								const value = done ? values?.[step?.slug] : null;
								return (
									<li
										key={step?.slug}
										className={ClassNames([
											style.step,
											done ? style.done : '',
											index === currentIndex ? style.current : ''
										])}
										aria-current={index === currentIndex ? 'step' : undefined}
									>
										<span className={style.dot}>
											<WizardIcon
												name={done ? 'check' : step?.icon}
												size={16}
												strokeWidth={done ? 2.8 : 1.8}
											/>
										</span>
										<div className={style.stepText}>
											<div className={style.stepLabel}>{step?.label}</div>
											<div
												className={ClassNames([
													style.stepDesc,
													value ? style.stepValue : ''
												])}
											>
												{value || step?.description}
											</div>
											{renderSubSteps(step)}
										</div>
									</li>
								);
							})}
						</ol>
						{help?.url ? (
							<div className={style.help}>
								<span className={style.helpIcon}>
									<WizardIcon name="book" size={16} />
								</span>
								<div>
									<strong>{help.title}</strong>
									{help.text}{' '}
									<a href={help.url} target="_blank" rel="noreferrer">
										{help.link}
										<WizardIcon name="ext" size={12} strokeWidth={2.2} />
									</a>
								</div>
							</div>
						) : null}
					</aside>
				) : null}
				<section className={style.main}>
					<div className={style.head}>
						{logo ? <span className={style.logo}>{logo}</span> : null}
						<div>
							<h1>{title}</h1>
							{desc ? (
								<p
									// eslint-disable-next-line react/no-danger
									dangerouslySetInnerHTML={{ __html: desc }}
								/>
							) : null}
						</div>
					</div>
					<div className={style.body}>{children}</div>
					{footer ? <div className={style.foot}>{footer}</div> : null}
				</section>
			</div>
		</div>
	);
}

export { WizardIcon };
