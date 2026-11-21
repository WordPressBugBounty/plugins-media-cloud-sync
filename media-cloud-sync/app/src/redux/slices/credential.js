/* eslint-disable no-param-reassign */
import { createSlice } from '@reduxjs/toolkit';

const initialState = {
	service: '',
	serviceLabel: '',
	cdn: {
		service: '',
		label: ''
	},
	config: {},
	configSource: 'database',
	bucketConfig: {},
	security: {},
	serviceEnabled: false,
	serviceError: ''
};

export const credentialSlice = createSlice({
	name: 'credential',
	initialState,
	reducers: {
		setConfiguration: (state, action) => {
			state.config = action.payload;
		},
		setConfigSource: (state, action) => {
			state.configSource = action.payload;
		},
		setService: (state, action) => {
			state.service = action.payload;
		},
		setServiceLabel: (state, action) => {
			state.serviceLabel = action.payload;
		},
		setCdn: (state, action) => {
			state.cdn = action.payload;
		},
		setCdnService: (state, action) => {
			state.cdn.service = action.payload;
		},
		setCdnLabel: (state, action) => {
			state.cdn.label = action.payload;
		},
		setBucketConfig: (state, action) => {
			state.bucketConfig = action.payload;
		},
		setSecurity: (state, action) => {
			state.security = action.payload;
		},
		setCredentials: (state, action) => {
			state.service = action.payload.service;
			state.serviceLabel = action.payload.serviceLabel;
			state.cdn = action.payload.cdn;
			state.config = action.payload.config;
			state.configSource = action.payload.configSource ?? 'database';
			state.bucketConfig = action.payload.bucketConfig;
			state.security = action.payload.security;
			if (action.payload.serviceEnabled !== undefined) {
				state.serviceEnabled = action.payload.serviceEnabled;
			} else if (action.payload.service) {
				state.serviceEnabled = true;
			}
			if (action.payload.serviceError !== undefined) {
				state.serviceError = action.payload.serviceError;
			}
		},
		setServiceHealth: (state, action) => {
			state.serviceEnabled = action.payload.serviceEnabled;
			state.serviceError = action.payload.serviceError ?? '';
		}
	}
});

// Action creators are generated for each case reducer function
export const {
	setConfiguration,
	setConfigSource,
	setService,
	setServiceLabel,
	setCdn,
	setCdnService,
	setCdnLabel,
	setBucketConfig,
	setCredentials,
	setSecurity,
	setServiceHealth
} = credentialSlice.actions;

export default credentialSlice;
