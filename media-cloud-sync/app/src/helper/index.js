import FetchWP from '../utils/fetchWP';
import getSchema from '../schema';
import { applyFilters } from './extend';

// True once an add-on registers itself; free only uses it to decide whether to show upsells.
export const isPro = () => !!applyFilters('wpmcs.isPro', false);

// A delivery entry's custom-domain toggle (a field whose child is `cdn_url`), if it has one.
export const getDomainField = (entry) =>
	entry?.fields?.find((field) =>
		field?.childrens?.some((child) => child?.name === 'cdn_url')
	);

// Mirrors Utils::delivery_needs_domain(): delivery on through an entry with a CNAME field but no domain.
export const deliveryNeedsDomain = (entry, settings) =>
	!!getDomainField(entry) &&
	!!settings?.rewrite_url &&
	!(settings?.enable_cdn && String(settings?.cdn_url || '').trim());

// Where a PRO preview points; the schema `upsell` entry decides (an add-on may point it at an in-app route).
export const getProUnlockCta = () => {
	const upsell = getSchema('upsell');
	return upsell?.cta?.to
		? { message: upsell.cta.message, label: upsell.cta.label, to: upsell.cta.to }
		: {
				message: upsell?.lockedUpgrade,
				label: upsell?.upgradeButton,
				href: upsell?.url
			};
};

export const ClassNames = (classes) => classes.join(' ');

export const escapeHtml = (text = '') =>
	String(text).replace(
		/[&<>"']/g,
		(char) =>
			({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]
	);

// Card schema `requires`: 'service' (storage chosen) or 'operational' (storage working).
export const isCardDisabled = (card, service, cloudOperational) => {
	if (card?.requires === 'service') return !service;
	if (card?.requires === 'operational') return !cloudOperational;
	return !!card?.disabled;
};

// Worpress API Init
export const fetchWP = new FetchWP({
	restURL: window.wpmcs_object.root,
	restNonce: window.wpmcs_object.api_nonce
});

// REST clients by schema `api` name; add-ons register their own namespace's client.
const apiClients = {};
export const registerApiClient = (name, client) => {
	apiClients[name] = client;
};
export const getApiClient = (name) => apiClients[name] || fetchWP;

// The sync method driving an action now: its saved method, or cron unless an add-on reports tick_supported (single source for SyncScreen and add-ons).
export const getActiveSyncMode = (actionStatus) => {
	const tickSupported = actionStatus?.tick_supported === true;
	const effectiveSyncMethod = resolveSyncMethod(actionStatus?.sync_method);
	return tickSupported ? effectiveSyncMethod : 'cron';
};

/**
 * A saved/picked sync method if the schema allows it, otherwise the schema's default method.
 * @param {string} method
 * @return {string}
 */
export const resolveSyncMethod = (method) => {
	const methods = getSchema('syncMethods');
	const fallback = methods.find((m) => m?.default)?.value || 'cron';
	const allowed = methods.find((m) => m?.value === method && !m?.preview);
	return allowed ? method : fallback;
};

export const validateFormField = (rules, value) => {
	let valid = true;
	let message = '';
	Object.keys(rules)?.forEach((rule) => {
		if (valid) {
			switch (rule) {
				case 'required':
					if (
						value === undefined ||
						value === null ||
						value === '' ||
						(Array.isArray(value) && value.length === 0)
					) {
						valid = false;
						message = wp.i18n.__(
							'This field is required',
							'media-cloud-sync'
						);
					}
					break;
				case 'length':
					if (
						rules?.[rule]?.length &&
						value?.length > rules?.[rule]
					) {
						valid = false;
						message = wp.i18n.__(
							'Maximum length exceeded',
							'media-cloud-sync'
						);
					}
					break;
				case 'regex':
					if (rules?.[rule]?.match) {
						if (!rules?.[rule]?.pattern?.test(value)) {
							valid = false;
							message = wp.i18n.__(
								'Invalid Format',
								'media-cloud-sync'
							);
						}
					} else if (rules?.[rule]?.pattern?.test(value)) {
						valid = false;
						message = wp.i18n.__(
							'Invalid Format',
							'media-cloud-sync'
						);
					}
					break;
				default:
					valid = true;
					break;
			}
		}
	});
	return { valid, message };
};

// Library-wide jobs (schema `libraryProgress`) show the dashboard's Synced ÷ Total media; others their run percentage.
export const jobPercentage = (screen, status, counts) => {
	if (screen?.libraryProgress) {
		const total = counts?.all?.total || 0;
		return total > 0 ? Math.min(100, ((counts?.all?.uploaded || 0) * 100) / total) : 0;
	}
	// A stopped run is idle, so its progress resets with its counters.
	return status?.status === 'stopped' ? 0 : status?.percentage || 0;
};

export const formatPercent = (percent) => Number(Number(percent || 0).toFixed(2));

/** Unix seconds (or ms) from API/DB to a locale datetime string for status checks. */
export const formatStatusLastChecked = (lastChecked) => {
	if (lastChecked === null || lastChecked === undefined || lastChecked === '') {
		return wp.i18n.__('Never', 'media-cloud-sync');
	}

	let ts = Number(lastChecked);
	if (!Number.isFinite(ts) || ts <= 0) {
		return wp.i18n.__('Never', 'media-cloud-sync');
	}

	if (ts < 1e12) {
		ts *= 1000;
	}

	const date = new Date(ts);
	if (Number.isNaN(date.getTime())) {
		return wp.i18n.__('Never', 'media-cloud-sync');
	}

	return date
		.toLocaleString('en-US', {
			year: 'numeric',
			month: '2-digit',
			day: '2-digit',
			hour: 'numeric',
			minute: 'numeric',
			second: 'numeric',
			hour12: true
		})
		.replace(',', '');
};

export const statusCheckTimestamp = (res) =>
	res?.lastChecked ?? Math.floor(Date.now() / 1000);

// Copies text to the clipboard with a fallback for non-secure (http) contexts.
// Empty strings are supported (e.g. region "" for Global); the copy-event fallback
// is used because execCommand on an empty textarea often fails.
export const copyToClipboard = (text) => {
	const value = text == null ? '' : String(text);

	const copyWithExecCommand = () =>
		new Promise((resolve, reject) => {
			try {
				const onCopy = (event) => {
					event.clipboardData.setData('text/plain', value);
					event.preventDefault();
				};
				document.addEventListener('copy', onCopy);
				const succeeded = document.execCommand('copy');
				document.removeEventListener('copy', onCopy);
				if (succeeded) {
					resolve();
				} else {
					reject(new Error('Copy command was unsuccessful'));
				}
			} catch (error) {
				reject(error);
			}
		});

	if (navigator?.clipboard?.writeText) {
		return navigator.clipboard.writeText(value).catch(() => copyWithExecCommand());
	}

	return copyWithExecCommand();
};
