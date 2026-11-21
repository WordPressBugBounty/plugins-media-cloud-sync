import { useCallback, useState } from 'react';
import { useDispatch } from 'react-redux';
import {
	applyConnectionStatusResponse,
	fetchConnectionStatus,
	initialConnectionStatusLoading,
	loadingKeysForCheck
} from '../helper/connectionStatus';

export default function useConnectionStatusCheck() {
	const dispatch = useDispatch();
	const [loading, setLoading] = useState(initialConnectionStatusLoading);

	const runCheck = useCallback(
		async (check) => {
			const keys = loadingKeysForCheck(check);
			setLoading((prev) => {
				const next = { ...prev };
				keys.forEach((key) => {
					next[key] = true;
				});
				return next;
			});

			try {
				const res = await fetchConnectionStatus(check);
				applyConnectionStatusResponse(dispatch, check, res);
				return res;
			} finally {
				setLoading((prev) => {
					const next = { ...prev };
					keys.forEach((key) => {
						next[key] = false;
					});
					return next;
				});
			}
		},
		[dispatch]
	);

	return { loading, runCheck };
}
