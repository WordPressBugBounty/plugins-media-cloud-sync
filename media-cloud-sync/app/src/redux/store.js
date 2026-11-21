import { combineSlices, configureStore } from '@reduxjs/toolkit';
import { credentialSlice } from './slices/credential';
import { tempCredentialSlice } from './slices/tempCredential';
import { commonSlice } from './slices/common';
import { syncSlice } from './slices/sync';

const rootReducer = combineSlices({
	credentials: credentialSlice.reducer,
	tempCredentials: tempCredentialSlice.reducer,
	common: commonSlice.reducer,
	sync: syncSlice.reducer
});

const store = configureStore({ reducer: rootReducer });

// Lets an add-on add its own state key to the shared store.
export const injectSlice = (reducerPath, reducer) => {
	rootReducer.inject({ reducerPath, reducer });
	store.dispatch({ type: 'wpmcs/injectSlice' });
};

export default store;
