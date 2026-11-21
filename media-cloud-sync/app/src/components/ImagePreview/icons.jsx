import React from 'react';

export function MediaLibraryIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<rect
				x="1.5"
				y="1.5"
				width="13"
				height="13"
				rx="1.5"
				stroke="currentColor"
				fill="none"
			/>
			<circle cx="5.2" cy="5.2" r="1.2" stroke="currentColor" fill="none" />
			<path
				d="M1.5 11L5.5 7.5L8.5 10L11 7.5L14.5 11.5"
				stroke="currentColor"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
		</svg>
	);
}

export function CopyIcon(props) {
	return (
		<svg
			width="16"
			height="18"
			viewBox="0 0 16 18"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<rect
				x="5.5"
				y="5.5"
				width="10"
				height="12"
				rx="1.5"
				stroke="currentColor"
				fill="none"
			/>
			<path
				d="M10.5 1.5H2.5C1.67157 1.5 1 2.17157 1 3V13.5"
				stroke="currentColor"
				fill="none"
			/>
		</svg>
	);
}

export function ZoomInIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<circle cx="7" cy="7" r="6" stroke="currentColor" fill="none" />
			<path
				d="M11.5 11.5L15 15M7 4.5V9.5M4.5 7H9.5"
				stroke="currentColor"
				strokeLinecap="round"
				fill="none"
			/>
		</svg>
	);
}

export function ZoomOutIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<circle cx="7" cy="7" r="6" stroke="currentColor" fill="none" />
			<path
				d="M11.5 11.5L15 15M4.5 7H9.5"
				stroke="currentColor"
				strokeLinecap="round"
				fill="none"
			/>
		</svg>
	);
}

export function RotateLeftIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				d="M7.5 1.5C11.09 1.5 14 4.41 14 8C14 11.59 11.09 14.5 7.5 14.5C3.91 14.5 1 11.59 1 8"
				stroke="currentColor"
				strokeLinecap="round"
				fill="none"
			/>
			<path
				d="M4 1L1 4L4 7"
				stroke="currentColor"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
		</svg>
	);
}

export function RotateRightIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				d="M8.5 1.5C4.91 1.5 2 4.41 2 8C2 11.59 4.91 14.5 8.5 14.5C12.09 14.5 15 11.59 15 8"
				stroke="currentColor"
				strokeLinecap="round"
				fill="none"
			/>
			<path
				d="M12 1L15 4L12 7"
				stroke="currentColor"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
		</svg>
	);
}

export function ResetIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				d="M14 8C14 11.3137 11.3137 14 8 14C4.68629 14 2 11.3137 2 8C2 4.68629 4.68629 2 8 2C10.0994 2 11.9483 3.07316 13.0359 4.7"
				stroke="currentColor"
				strokeLinecap="round"
				fill="none"
			/>
			<path
				d="M13.5 1.5V5H10"
				stroke="currentColor"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
		</svg>
	);
}

export function ExternalLinkIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				d="M6.5 2.5H2.5C1.94772 2.5 1.5 2.94772 1.5 3.5V13.5C1.5 14.0523 1.94772 14.5 2.5 14.5H12.5C13.0523 14.5 13.5 14.0523 13.5 13.5V9.5"
				stroke="currentColor"
				fill="none"
			/>
			<path
				d="M9.5 1.5H14.5V6.5M14.5 1.5L7.5 8.5"
				stroke="currentColor"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
		</svg>
	);
}

export function CloseIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				d="M2 2L14 14M2 14L14 2"
				stroke="currentColor"
				strokeWidth="1.4"
				strokeLinecap="round"
				fill="none"
			/>
		</svg>
	);
}

export function DownloadIcon(props) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
			{...props}
		>
			<path
				d="M8 1.5V10.5M8 10.5L4.5 7M8 10.5L11.5 7"
				stroke="currentColor"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
			<path
				d="M1.5 12.5V13.5C1.5 14.0523 1.94772 14.5 2.5 14.5H13.5C14.0523 14.5 14.5 14.0523 14.5 13.5V12.5"
				stroke="currentColor"
				strokeLinecap="round"
				fill="none"
			/>
		</svg>
	);
}
