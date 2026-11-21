import React from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { createSlice } from '@reduxjs/toolkit';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { toast } from 'react-toastify';

import store, { injectSlice } from '../redux/store';
import { setSyncData, setSyncStatus } from '../redux/slices/sync';
import { setCounts } from '../redux/slices/common';
import getSchema from '../schema';
import {
	ClassNames,
	copyToClipboard,
	escapeHtml,
	fetchWP,
	getActiveSyncMode,
	getApiClient,
	registerApiClient,
	resolveSyncMethod,
	validateFormField
} from '../helper';
import FetchWP from '../utils/fetchWP';
import { PrimaryButton, SecondaryButton } from '../components/Buttons';
import TextInput from '../components/TextInput';
import ToggleButton from '../components/ToggleButton';
import ConfirmDialog from '../components/ConfirmDialog';
import ImagePreview from '../components/ImagePreview';
import BackLink from '../components/BackLink';
import Spinner from '../components/Spinner';
import WizardShell from '../components/WizardShell';
import WizardIcon from '../components/WizardShell/icons';
import ProLock from '../components/ProLock';
import Slot from '../components/Slot';
import MainLoader from '../components/Loading';
import SyncMethodToggle from '../components/SyncScreen/SyncMethodToggle';
import SyncScreen from '../pages/SyncScreen';
import ConfigureFooter from '../pages/Configure/Footer';
import SetCredentials from '../pages/Configure/setCredentials';
import SetBucket from '../pages/Configure/setBucket';
import configureStyle from '../pages/Configure/style.scss';

// Shared with add-ons (webpack externals) so there is one React, store, router and toast instance.
window.wpmcs = {
	vendor: {
		React,
		ReactRedux: { useDispatch, useSelector },
		RTK: { createSlice },
		Router: { Link, useLocation, useNavigate, useParams },
		Toastify: { toast }
	},
	store,
	injectSlice,
	api: { get: getApiClient, register: registerApiClient, FetchWP },
	components: {
		PrimaryButton,
		SecondaryButton,
		TextInput,
		ToggleButton,
		ConfirmDialog,
		ImagePreview,
		BackLink,
		Spinner,
		WizardShell,
		WizardIcon,
		ProLock,
		Slot,
		MainLoader,
		SyncMethodToggle
	},
	pages: { SyncScreen, ConfigureFooter, SetCredentials, SetBucket },
	styles: { configure: configureStyle },
	helpers: {
		getSchema,
		fetchWP,
		ClassNames,
		escapeHtml,
		validateFormField,
		copyToClipboard,
		getActiveSyncMode,
		resolveSyncMethod
	},
	actions: { setSyncData, setSyncStatus, setCounts }
};
