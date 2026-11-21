import React from 'react';
import { Provider } from 'react-redux';
import ReactDOM from 'react-dom/client';
import './runtime';
import Main from './Main';
import store from './redux/store';

// Render after DOMContentLoaded so add-on scripts (loaded after this one) have registered.
const mount = () => {
	const el = document.getElementById('wpmcs_ui_root');
	if (!el) return;
	ReactDOM.createRoot(el).render(
		<Provider store={store}>
			<Main />
		</Provider>
	);
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mount);
} else {
	mount();
}
