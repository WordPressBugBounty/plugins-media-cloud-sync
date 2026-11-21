import React from 'react';
import { HashRouter, Route, Routes } from 'react-router-dom';

import { ToastContainer } from 'react-toastify';

import 'react-toastify/dist/ReactToastify.css';
import { useDispatch, useSelector } from 'react-redux';
import style from './backend.scss';
import Header from './components/Header';
import Configure from './pages/Configure';
import Dashboard from './pages/Dashboard';
import Tools from './pages/Tools';
import MediaSettings from './pages/MediaSettings';
import ImportExport from './pages/ImportExport';
import { fetchWP } from './helper';
import { applyFilters, doAction } from './helper/extend';
import { setCredentials } from './redux/slices/credential';
import { setCommonData } from './redux/slices/common';
import { setSyncData } from './redux/slices/sync';
import MainLoader from './components/Loading';
import getSchema from './schema';
import ChooseCDN from './pages/ChooseCDN';
import SyncScreen from './pages/SyncScreen';
import useSyncPolling from './hooks/useSyncPolling';
import ToolPage from './pages/ToolPage';
import UpgradeNotice from './components/UpgradeNotice';
import Slot from './components/Slot';

const getLoadErrorMessage = (error) => {
	if (error?.message) {
		return error.message;
	}

	if (typeof error === 'string') {
		return error;
	}

	return wp.i18n.__(
		'Unable to load plugin settings. Please refresh the page or check your connection.',
		'media-cloud-sync'
	);
};

export default function Main() {
	const dispatch = useDispatch();
	const settingsDefault = getSchema('settingsDefaultValues');
	const cdnServers = getSchema('cdnServers');
	const savedCredentials = useSelector((state) => state?.credentials);
	const savedCommonData = useSelector((state) => state?.common);
	const savedSyncData = useSelector((state) => state?.sync);
	const [loader, setLoader] = React.useState(true);
	const [loadError, setLoadError] = React.useState('');
	const [retryCount, setRetryCount] = React.useState(0);

	React.useEffect(() => {
		const getData = async () => {
			setLoader(true);
			setLoadError('');

			try {
				const settings = await fetchWP.get('getSettings/');
				const credentialsLength = Object.keys(
					settings?.credentials || {}
				).length;
				const service =
					settings?.credentials?.service || savedCredentials?.service;

				let currentConfig = {};
				if (credentialsLength) {
					currentConfig = {
						service,
						serviceLabel: settings?.credentials?.serviceLabel || '',
						cdn: {
							// A saved-but-empty provider stays empty; only legacy credentials without one get the default.
							service: settings?.credentials?.cdn
								? settings.credentials.cdn.service || ''
								: Object.keys(cdnServers?.[service] || {}).find(
										(server) =>
											cdnServers?.[service]?.[server]
												?.default === true
									) || '',
							label: settings?.credentials?.cdn?.label || ''
						},
						config: {
							...savedCredentials?.config,
							...settings?.credentials?.config
						},
						configSource:
							settings?.credentials?.configSource ??
							savedCredentials?.configSource ??
							'database',
						bucketConfig: {
							...savedCredentials?.bucketConfig,
							...settings?.credentials?.bucketConfig
						},
						security: {
							...savedCredentials?.security,
							...settings?.credentials?.security
						},
						serviceEnabled:
							settings?.serviceEnabled !== undefined
								? settings.serviceEnabled
								: !!service,
						serviceError: settings?.serviceError ?? ''
					};
					dispatch(setCredentials(currentConfig));
				}

				if (Object.keys(settings?.common || {}).length) {
					const propApiCombined = {
						...savedCommonData?.settings,
						...settings?.common?.settings
					};

					const newSettings = {};
					const missingDefaults = {};
					if (
						currentConfig?.service &&
						settingsDefault?.[currentConfig.service]
					) {
						Object.keys(
							settingsDefault[currentConfig.service]
						).forEach((key) => {
							if (key in propApiCombined) {
								newSettings[key] = propApiCombined[key];
							} else {
								newSettings[key] = settingsDefault[currentConfig.service][key];
								missingDefaults[key] = newSettings[key];
							}
						});
					}

					const commonConfig = {
						...savedCommonData,
						...settings?.common,
						status: {
							...savedCommonData?.status,
							...settings?.common?.status
						},
						settings: newSettings
					};

					// Saves merge server-side, so only the missing defaults are sent, and only when there are any.
					if (Object.keys(missingDefaults).length) {
						await fetchWP.post('saveConfig', {
							action: 'settings',
							background: true,
							settings: missingDefaults
						});
					}
					dispatch(setCommonData(commonConfig));
				}

				if (Object.keys(settings?.sync || {}).length) {
					const syncConfig = {
						...savedSyncData,
						...settings?.sync
					};
					dispatch(setSyncData(syncConfig));
				}

				// Add-ons read their own parts of the settings response here.
				doAction('wpmcs.settingsLoaded', settings, dispatch);
			} catch (error) {
				setLoadError(getLoadErrorMessage(error));
			} finally {
				setLoader(false);
			}
		};

		getData();
	}, [retryCount]);

	useSyncPolling();

	const { is_db_upgrade_required: upgrade } = window.wpmcs_object;

	// Add-on screens: [{ path, Component }].
	const addonRoutes = React.useMemo(() => applyFilters('wpmcs.routes', []), []);
	const schemaRoutes = getSchema('routes');
	// Each job's schema `path` is its route; jobs living on a custom page (schema `routes`) are skipped.
	const toolPages = Object.entries(getSchema('toolPages') || {});
	const jobRoutes = Object.entries(getSchema('syncScreen')).filter(
		([, screen]) =>
			screen?.path?.startsWith('/') &&
			!schemaRoutes.includes(screen.path.slice(1))
	);

	return (
		<div className={style.innerContent}>
			<HashRouter>
				<Slot name="app.root" />
				<Header loader={loader || upgrade} />
				{loader ? (
					<MainLoader />
				) : loadError ? (
					<MainLoader
						error={loadError}
						onRetry={() => setRetryCount((count) => count + 1)}
					/>
				) : (
					<div className={style.pageContainer}>
						<Slot name="app.pageTop" />
						{upgrade ? (
							<UpgradeNotice />
						) : (
							<React.Suspense fallback={<MainLoader />}>
							<Routes>
								<Route
									path="/"
									exact
									element={<Dashboard />}
								/>
								<Route
									path="/configure"
									exact
									element={
										<Configure
											isConfigured={
												!!savedCredentials?.service
											}
										/>
									}
								/>
								<Route
									path="/choose_cdn"
									exact
									element={<ChooseCDN />}
								/>
								<Route
									path="/settings"
									exact
									element={<MediaSettings />}
								/>
								<Route
									path="/import-export"
									exact
									element={<ImportExport />}
								/>
								{jobRoutes.map(([action, screen]) => (
									<Route
										key={action}
										path={screen.path}
										element={
											<SyncScreen
												key={action}
												action={action}
											/>
										}
									/>
								))}
								{toolPages.map(([slug, page]) => (
									<Route
										key={slug}
										path={page.path}
										element={<ToolPage key={slug} slug={slug} />}
									/>
								))}
								{getSchema('tools').length ? (
									<Route path="/tools" exact element={<Tools />} />
								) : null}
								{addonRoutes.map(({ path, Component }) => (
									<Route key={path} path={path} element={<Component />} />
								))}
							</Routes>
							</React.Suspense>
						)}
						<ToastContainer
							position="top-right"
							autoClose={2000}
							hideProgressBar={false}
							newestOnTop={false}
							closeOnClick={false}
							rtl={false}
							pauseOnFocusLoss={false}
							draggable
							pauseOnHover
							theme="light"
						/>
					</div>
				)}
			</HashRouter>
		</div>
	);
}
