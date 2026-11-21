import React, { useMemo, useRef } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { setSyncData } from '../redux/slices/sync';
import { fetchWP } from '../helper';
import { setCounts } from '../redux/slices/common';

const shouldPollSyncStatus = (status) =>
	Object.values(status || {}).some(
		(sync) =>
			sync?.status === 'running' ||
			sync?.stop_requested ||
			sync?.pause_requested
	);

const useSyncPolling = () => {
	const dispatch = useDispatch();
	const syncData = useSelector((state) => state?.sync);

	const isPollingRef = useRef(false);
	const timeoutIdRef = useRef(null);
	const isMountedRef = useRef(true);

	const needsPolling = useMemo(
		() => shouldPollSyncStatus(syncData),
		[syncData]
	);

	React.useEffect(() => {
		isMountedRef.current = true;

		const poll = async () => {
			if (!isMountedRef.current) return;

			try {
				const response = await fetchWP.get('sync/status');
				if (response?.success) {
					if (response?.status) {
						dispatch(setSyncData(response?.status));

						if (shouldPollSyncStatus(response.status)) {
							// schedule next poll only while sync is active or finishing control actions
							timeoutIdRef.current = setTimeout(poll, 2000);
						} else {
							// stop polling
							isPollingRef.current = false;
						}
					}

					if (response?.counts) {
						// Update counts if provided
						dispatch(setCounts(response.counts));
					}
				}
			} catch (err) {
				console.error('Sync polling error:', err);
				isPollingRef.current = false;
			}
		};

		// Start polling only when needed; do not restart on every progress update.
		if (needsPolling && !isPollingRef.current) {
			isPollingRef.current = true;
			timeoutIdRef.current = setTimeout(poll, 3000); // first poll after 3s
		}

		if (!needsPolling) {
			clearTimeout(timeoutIdRef.current);
			isPollingRef.current = false;
		}

		return () => {
			isMountedRef.current = false;
			clearTimeout(timeoutIdRef.current);
			isPollingRef.current = false;
		};
	}, [needsPolling, dispatch]);

	return null;
};

export default useSyncPolling;
