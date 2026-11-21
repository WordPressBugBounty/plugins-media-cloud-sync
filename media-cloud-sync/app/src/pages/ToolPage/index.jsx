import React from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'react-toastify';

import style from './style.scss';
import getSchema from '../../schema';
import { ClassNames, getApiClient } from '../../helper';
import BackLink from '../../components/BackLink';
import ConfirmDialog from '../../components/ConfirmDialog';
import SchemaOptions from '../../components/SchemaOptions';
import Spinner from '../../components/Spinner';

const client = (api) => getApiClient(api);

const fieldDefaults = (fields) =>
	Object.fromEntries((fields || []).map((field) => [field.name, field.default ?? '']));

/**
 * A card of a tool page: optional status read from `status.endpoint` (badge, detail, stats), schema
 * fields, action buttons that POST the field values (plus `extra`) to their endpoint, and a `link`.
 */
function ToolCard({ card }) {
	const [status, setStatus] = React.useState(card.status ? null : {});
	const [values, setValues] = React.useState(() => fieldDefaults(card.fields));
	const [busy, setBusy] = React.useState('');
	const [confirming, setConfirming] = React.useState(null);

	const applyStatus = (next) => {
		setStatus(next || {});
		if (next?.values) {
			setValues((current) => ({ ...current, ...next.values }));
		}
	};

	const loadStatus = React.useCallback(async () => {
		if (!card.status?.endpoint) return;
		setStatus(null);
		try {
			const response = await client(card.status.api).get(card.status.endpoint);
			applyStatus(response?.success ? response : { message: response?.message, unavailable: true });
		} catch (error) {
			applyStatus({ message: error?.message, unavailable: true });
		}
	}, [card.status?.endpoint, card.status?.api]);

	React.useEffect(() => {
		loadStatus();
	}, [loadStatus]);

	const runAction = async (action) => {
		setConfirming(null);
		setBusy(action.id);
		try {
			const response = await client(action.api)[action.method === 'DELETE' ? 'delete' : 'post'](
				action.endpoint,
				{ ...values, ...(action.extra || {}) }
			);
			if (response?.success) {
				toast.success(response.message, { autoClose: 4000 });
				if (response.status) {
					applyStatus(response.status);
				} else {
					await loadStatus();
				}
			} else {
				toast.error(
					response?.message || wp.i18n.__('Something went wrong. Please try again.', 'media-cloud-sync'),
					{ autoClose: 6000 }
				);
			}
		} catch (error) {
			toast.error(error?.message || wp.i18n.__('Something went wrong. Please try again.', 'media-cloud-sync'), {
				autoClose: 6000
			});
		}
		setBusy('');
	};

	const loading = status === null;
	const unavailable = !!status?.unavailable;
	const visibleActions = (card.actions || []).filter(
		(action) => !(status?.hideActions || []).includes(action.id)
	);
	const showFooter = !loading && (visibleActions.length || card.link);

	return (
		<section className={style.card} aria-labelledby={`tool-card-${card.id}`}>
			<div className={ClassNames([style.cardHead, card.fields?.length ? style.divided : null])}>
				<div>
					<h3 id={`tool-card-${card.id}`}>{card.title}</h3>
					{card.description ? <p>{card.description}</p> : null}
				</div>
				{status?.badge?.label ? (
					<span className={ClassNames([style.badge, style[status.badge.tone] || null])}>
						<span className={style.dot} />
						{status.badge.label}
					</span>
				) : null}
			</div>

			{loading ? <Spinner block /> : null}
			{!loading && status?.detail ? (
				<div className={style.statusLine}>
					{/* One text run, so a wrapped line keeps the separator next to its text. */}
					<span className={style.detailText}>
						<span className={style.detail}>{status.detail}</span>
						{status.detailMeta ? (
							<>
								<span className={style.separator} aria-hidden="true" />
								<span className={style.detailMeta}>{status.detailMeta}</span>
							</>
						) : null}
					</span>
					{status.detailLink?.url ? (
						<a
							className={style.detailLink}
							href={status.detailLink.url}
							title={status.detailLink.url}
							target="_blank"
							rel="noopener noreferrer"
						>
							<span>{status.detailLink.label}</span>
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<path d="M7 17 17 7M9 7h8v8" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
							</svg>
						</a>
					) : null}
					{card.status ? (
						<button type="button" className={style.linkButton} onClick={loadStatus}>
							{wp.i18n.__('Refresh', 'media-cloud-sync')}
						</button>
					) : null}
				</div>
			) : null}
			{!loading && status?.message ? (
				<div className={style.notice} role="alert">
					{status.message}
				</div>
			) : null}

			{!loading && status?.stats?.length ? (
				<div className={style.stats}>
					{status.stats.map((stat) => (
						<div key={stat.label}>
							<span>{stat.label}</span>
							<strong className={style[stat.tone] || null}>{stat.value}</strong>
						</div>
					))}
				</div>
			) : null}

			{!loading && !unavailable && card.fields?.length ? (
				<div className={style.fields}>
					<SchemaOptions
						options={card.fields}
						values={values}
						hints={status?.hints}
						idPrefix={card.id}
						onChange={(name, value) => setValues((current) => ({ ...current, [name]: value }))}
					/>
					{card.note ? <p className={style.note}>{card.note}</p> : null}
				</div>
			) : null}

			{showFooter ? (
				<div className={ClassNames([style.actions, card.link ? style.plain : null])}>
					{visibleActions.map((action) => (
						<button
							key={action.id}
							type="button"
							className={ClassNames([
								style.button,
								style[action.variant || 'primary'],
								busy === action.id ? style.loading : null
							])}
							disabled={!!busy || unavailable}
							onClick={() => (action.confirm ? setConfirming(action) : runAction(action))}
						>
							{action.label}
							<span className={style.loader} />
						</button>
					))}
					{card.link ? (
						<Link to={card.link.path} className={ClassNames([style.button, style.outline])}>
							{card.link.label}
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<path d="M9 6l6 6-6 6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
							</svg>
						</Link>
					) : null}
					{unavailable && card.status && visibleActions.length ? (
						<button type="button" className={style.linkButton} onClick={loadStatus}>
							{wp.i18n.__('Check again', 'media-cloud-sync')}
						</button>
					) : card.actionsNote ? (
						<span className={ClassNames([style.actionsNote, card.link ? style.inline : null])}>
							{card.actionsNote}
						</span>
					) : null}
				</div>
			) : null}

			{confirming ? (
				<ConfirmDialog
					open
					danger={!!confirming.confirm.danger}
					title={confirming.confirm.title}
					message={confirming.confirm.message}
					confirmLabel={confirming.confirm.confirmLabel || confirming.label}
					onCancel={() => setConfirming(null)}
					onConfirm={() => runAction(confirming)}
				/>
			) : null}
		</section>
	);
}

// Schema-driven page for a tool with its own settings: cards from `toolPages[slug]` and an aside.
export default function ToolPage({ slug }) {
	const page = getSchema('toolPages')?.[slug] || {};

	return (
		<div className={style.toolPage}>
			<BackLink to={page.back?.path || '/tools'} label={page.back?.label} />
			<header className={style.hero}>
				{page.icon ? (
					<span className={style.heroIcon} style={{ backgroundColor: page.iconBgColor }}>
						<img src={page.icon} alt="" />
					</span>
				) : null}
				<div>
					<h2>{page.title}</h2>
					{page.description ? <p>{page.description}</p> : null}
				</div>
			</header>

			{page.unavailable ? (
				<section className={style.unavailable}>
					<span className={style.infoIcon}>
						<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
							<circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="1.8" />
							<path d="M12 11v5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
							<circle cx="12" cy="7.6" r="1.2" fill="currentColor" />
						</svg>
					</span>
					<div>
						<h3>{page.unavailable.title}</h3>
						{page.unavailable.text ? <p>{page.unavailable.text}</p> : null}
					</div>
				</section>
			) : (
				<div className={ClassNames([style.layout, page.aside?.length ? style.withAside : null])}>
					<div className={style.main}>
						{(page.cards || []).map((card) => (
							<ToolCard key={card.id} card={card} />
						))}
					</div>
					{page.aside?.length ? (
						<aside className={style.aside}>
							{page.aside.map((box) => (
								<section key={box.title} className={style.asideBox}>
									<h3>{box.title}</h3>
									{box.text ? <p>{box.text}</p> : null}
									{box.items?.length ? (
										<ul>
											{box.items.map((item) => (
												<li key={item}>{item}</li>
											))}
										</ul>
									) : null}
									{box.rows?.length ? (
										<dl className={style.rows}>
											{box.rows.map((row) => (
												<React.Fragment key={row.label}>
													<dt>{row.label}</dt>
													<dd>{row.value}</dd>
												</React.Fragment>
											))}
										</dl>
									) : null}
									{box.footnote ? <p className={style.footnote}>{box.footnote}</p> : null}
								</section>
							))}
						</aside>
					) : null}
				</div>
			)}
		</div>
	);
}
