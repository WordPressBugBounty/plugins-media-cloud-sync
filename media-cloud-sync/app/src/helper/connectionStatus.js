import { fetchWP, statusCheckTimestamp } from './index';
import { setStatus } from '../redux/slices/common';
import { setServiceHealth } from '../redux/slices/credential';

/** Saved-connection status checks (maps API `check` param to Redux status keys). */
export const CONNECTION_STATUS_CHECKS = {
	storage: {
		check: 'storage',
		statusKey: 'storageCredentials',
		loadingKey: 'storage',
		updatesServiceHealth: true,
		failedLabel: wp.i18n.__('Storage check failed', 'media-cloud-sync'),
		defaultMessage: wp.i18n.__(
			'Click the button to verify storage credentials and write access.',
			'media-cloud-sync'
		)
	},
	cdn: {
		check: 'cdn',
		statusKey: 'cdnRead',
		loadingKey: 'cdn',
		failedLabel: wp.i18n.__('Delivery check failed', 'media-cloud-sync'),
		defaultMessage: wp.i18n.__(
			'Click the button to check the read status of your Provider.',
			'media-cloud-sync'
		)
	}
};

const CONNECTION_STATUS_LOADING_KEYS = ['storage', 'cdn'];

export const initialConnectionStatusLoading = () =>
	CONNECTION_STATUS_LOADING_KEYS.reduce(
		(acc, key) => ({ ...acc, [key]: false }),
		{}
	);

/**
 * @param {'storage'|'cdn'|'all'} check
 */
export const fetchConnectionStatus = (check) =>
	fetchWP.post('permissions/status', { check });

const applySingleStatus = (dispatch, statusKey, res, updatesServiceHealth) => {
	dispatch(
		setStatus({
			key: statusKey,
			value: {
				status: res?.success,
				message: res?.message,
				lastChecked: statusCheckTimestamp(res)
			}
		})
	);

	if (updatesServiceHealth) {
		dispatch(
			setServiceHealth({
				serviceEnabled: !!res?.success,
				serviceError: res?.success ? '' : res?.message || ''
			})
		);
	}
};

/**
 * Apply API response to Redux (single check or `checks` from check=all).
 * @param {import('redux').Dispatch} dispatch
 * @param {'storage'|'cdn'|'all'} check
 * @param {object} response
 */
export const applyConnectionStatusResponse = (dispatch, check, response) => {
	if (response?.checks) {
		Object.entries(response.checks).forEach(([statusKey, res]) => {
			const meta = Object.values(CONNECTION_STATUS_CHECKS).find(
				(item) => item.statusKey === statusKey
			);
			applySingleStatus(
				dispatch,
				statusKey,
				res,
				meta?.updatesServiceHealth
			);
		});
		return;
	}

	const meta = CONNECTION_STATUS_CHECKS[check];
	if (!meta) {
		return;
	}

	applySingleStatus(
		dispatch,
		meta.statusKey,
		response,
		meta.updatesServiceHealth
	);
};

/** Which loading flags to set for a given check param. */
export const loadingKeysForCheck = (check) => {
	if (check === 'all') {
		return CONNECTION_STATUS_LOADING_KEYS;
	}
	const meta = CONNECTION_STATUS_CHECKS[check];
	return meta ? [meta.loadingKey] : [];
};
