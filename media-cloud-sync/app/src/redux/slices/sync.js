/* eslint-disable no-param-reassign */
import { createSlice } from '@reduxjs/toolkit';

const initialState = {};

export const syncSlice = createSlice({
	name: 'sync',
	initialState,
	reducers: {
		setSyncData: (state, action) => action?.payload,
		setSyncStatus: (state, action) => {
			const { key, value } = action.payload;
			state[key] = { ...value };
		}
	}
});

// Action creators are generated for each case reducer function
export const { setSyncData, setSyncStatus } = syncSlice.actions;

export default syncSlice;
