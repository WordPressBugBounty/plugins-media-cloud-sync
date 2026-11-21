/* eslint-disable no-param-reassign */
import { createSlice } from '@reduxjs/toolkit';

const initialState = {
	// From the page so the header is right before settings load.
	version: window.wpmcs_object?.version || '',
	counts: {},
	status: {
		cdnRead: {
			status: false,
			message: '',
			lastChecked: null
		},
		storageCredentials: {
			status: false,
			message: '',
			lastChecked: null
		}
	},
	settings: {}
};

export const commonSlice = createSlice({
	name: 'common',
	initialState,
	reducers: {
		setCommonData: (state, action) => {
			state.version = action.payload?.version || state.version;
			state.counts = action.payload?.counts;
			state.status = action.payload?.status;
			state.settings = action.payload?.settings;
		},
		setStatus: (state, action) => {
			const { key, value } = action.payload;

			if (state.status[key]) {
				state.status[key] = {
					...state.status[key],
					...value
				};
			} else {
				state.status[key] = value;
			}
		},
		setSettingsData: (state, action) => {
			state.settings = action.payload;
		},
		setCounts: (state, action) => {
			state.counts = action.payload;
		}
	}
});

// Action creators are generated for each case reducer function
export const { setCommonData, setStatus, setSettingsData, setCounts } =
	commonSlice.actions;

export default commonSlice;
