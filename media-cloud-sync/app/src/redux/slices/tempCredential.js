/* eslint-disable no-param-reassign */
import { createSlice } from '@reduxjs/toolkit';

const initialState = {
	service: 's3',
	serviceLabel: '',
	cdn: { service: '', label: '' },
	config: {},
	configSource: 'database',
	buckets: [],
	bucketData: {
		config: {},
		addNewConfig: {},
		addNew: false,
		bucketNameChoose: true
	},
	security: {}
};

export const tempCredentialSlice = createSlice({
	name: 'tempCredential',
	initialState,
	reducers: {
		setTemporaryService: (state, action) => {
			state.service = action.payload;
		},
		setTemporaryServiceLabel: (state, action) => {
			state.serviceLabel = action.payload;
		},
		setTemporaryConfig: (state, action) => {
			state.config = action.payload;
		},
		setTemporaryConfigSource: (state, action) => {
			state.configSource = action.payload;
		},
		setTemporaryBuckets: (state, action) => {
			state.buckets = action.payload;
		},
		setTemporaryBucketData: (state, action) => {
			state.bucketData = action.payload;
		},
		setTemporaryBucketConfig: (state, action) => {
			state.bucketData.config = action.payload;
		},
		setTemporaryAddNewBucketConfig: (state, action) => {
			state.bucketData.addNewConfig = action.payload;
		},
		setTemporaryAddNew: (state, action) => {
			state.bucketData.addNew = action.payload;
		},
		setTemporarySecurity: (state, action) => {
			state.security = action.payload;
		},
		setTemporaryData: (state, action) => {
			state.service = action.payload.service;
			state.serviceLabel = action.payload.serviceLabel;
			state.cdn = action.payload.cdn;
			state.config = action.payload.config;
			state.configSource = action.payload.configSource ?? 'database';
			state.buckets = action.payload.buckets;
			state.bucketData = action.payload.bucketData;
			state.security = action.payload.security;
		}
	}
});

// Action creators are generated for each case reducer function
export const {
	setTemporaryService,
	setTemporaryServiceLabel,
	setTemporaryConfig,
	setTemporaryConfigSource,
	setTemporaryBuckets,
	setTemporaryBucketConfig,
	setTemporaryData,
	setTemporaryBucketData,
	setTemporaryAddNewBucketConfig,
	setTemporaryAddNew,
	setTemporarySecurity
} = tempCredentialSlice.actions;

// Default `actions` prop for SetCredentials/SetBucket/CreateBucket/ChooseBucket
// (app/src/pages/configure/setCredentials.jsx, setBucket.jsx, app/src/components/CreateBucket,
// app/src/components/ChooseBucket) when the caller doesn't pass its own `slice`/`actions` —
// every existing onboarding render site (Configure.jsx) relies on this default, so those
// components keep working against this slice unmodified. A second consumer (the Bucket-to-
// Bucket destination form) passes its own `slice`/`actions` pointing at destinationCredential.js
// instead, using this same key shape.
export const defaultTemporaryActions = {
	setTemporaryConfig,
	setTemporaryConfigSource,
	setTemporaryBucketData,
	setTemporaryBucketConfig,
	setTemporaryAddNewBucketConfig
};

export default tempCredentialSlice;
