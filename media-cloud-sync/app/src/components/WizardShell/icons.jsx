import React from 'react';

// Stroke icons for the wizard; schema `icon` keys pick one by name.
const PATHS = {
	check: <path d="M5 12.5l4.5 4.5L19 7.5" />,
	left: <path d="M15 6l-6 6 6 6" />,
	right: <path d="M9 6l6 6-6 6" />,
	cloud: (
		<path d="M7 18h10a4 4 0 0 0 .5-7.97A5.5 5.5 0 0 0 6.6 9.1 4.5 4.5 0 0 0 7 18Z" />
	),
	sparkle: (
		<>
			<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z" />
			<path d="M19 16v4M17 18h4" />
		</>
	),
	plug: <path d="M9 7V3M15 7V3M7 7h10v4a5 5 0 0 1-10 0V7ZM12 16v5" />,
	save: (
		<>
			<path d="M5 4h11l3 3v13H5V4Z" />
			<path d="M8 4v5h7V4M8 20v-6h8v6" />
		</>
	),
	globe: (
		<>
			<circle cx="12" cy="12" r="9" />
			<path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
		</>
	),
	key: (
		<>
			<circle cx="8" cy="15" r="4" />
			<path d="M11 12l8-8M16 7l2.5 2.5M18 5l2 2" />
		</>
	),
	database: (
		<>
			<ellipse cx="12" cy="6" rx="7" ry="3" />
			<path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3" />
		</>
	),
	databasePlus: (
		<>
			<ellipse cx="11" cy="6" rx="7" ry="3" />
			<path d="M4 6v12c0 1.7 3.1 3 7 3M18 6v5M4 12c0 1.7 3.1 3 7 3M18 15v6M15 18h6" />
		</>
	),
	shield: <path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3Z" />,
	shieldCheck: (
		<>
			<path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3Z" />
			<path d="M9 12l2 2 4-4" />
		</>
	),
	lock: (
		<>
			<rect x="5" y="11" width="14" height="10" rx="2" />
			<path d="M8 11V8a4 4 0 0 1 8 0v3" />
		</>
	),
	code: (
		<>
			<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" />
			<path d="M14 3v5h5M10 13l-2 2 2 2M14 13l2 2-2 2" />
		</>
	),
	link: (
		<path d="M10 14l4-4M9 7l1-1a4 4 0 0 1 6 6l-1 1M15 17l-1 1a4 4 0 0 1-6-6l1-1" />
	),
	list: <path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01" />,
	pencil: <path d="M4 20h4L19 9l-4-4L4 16v4Z" />,
	upload: <path d="M12 16V4M7 9l5-5 5 5M5 20h14" />,
	trash: <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />,
	search: (
		<>
			<circle cx="11" cy="11" r="7" />
			<path d="m20 20-4-4" />
		</>
	),
	user: (
		<>
			<circle cx="12" cy="8" r="4" />
			<path d="M4 21a8 8 0 0 1 16 0" />
		</>
	),
	zap: <path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z" />,
	info: (
		<>
			<circle cx="12" cy="12" r="9" />
			<path d="M12 11v5M12 8v.01" />
		</>
	),
	warn: (
		<>
			<path d="M12 3 2.5 20h19L12 3Z" />
			<path d="M12 10v4M12 17v.01" />
		</>
	),
	book: (
		<path d="M5 5.5A1.5 1.5 0 0 1 6.5 4H19v14H6.5A1.5 1.5 0 0 0 5 19.5v-14ZM5 19.5A1.5 1.5 0 0 0 6.5 21H19" />
	),
	ext: <path d="M9 5h10v10M19 5 6 18" />,
	clock: (
		<>
			<circle cx="12" cy="12" r="9" />
			<path d="M12 7v5l3 2" />
		</>
	),
	map: (
		<>
			<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z" />
			<circle cx="12" cy="9" r="2.5" />
		</>
	),
	undo: (
		<>
			<path d="M9 14 4 9l5-5" />
			<path d="M4 9h10a6 6 0 0 1 0 12h-3" />
		</>
	),
	x: <path d="M7 7l10 10M17 7 7 17" />,
	copy: (
		<>
			<rect x="9" y="9" width="11" height="11" rx="2" />
			<path d="M5 15V5a2 2 0 0 1 2-2h10" />
		</>
	),
	folder: (
		<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />
	),
	file: (
		<>
			<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" />
			<path d="M14 3v5h5" />
		</>
	),
	image: (
		<>
			<rect x="3" y="5" width="18" height="14" rx="2" />
			<path d="m3 16 5-5 4 4 3-3 6 6" />
		</>
	),
	film: (
		<>
			<rect x="3" y="5" width="18" height="14" rx="2" />
			<path d="m10 9 5 3-5 3V9Z" />
		</>
	),
	music: (
		<>
			<path d="M9 18V5l11-2v13" />
			<circle cx="6" cy="18" r="3" />
			<circle cx="17" cy="16" r="3" />
		</>
	),
	archive: (
		<>
			<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" />
			<path d="M11 7h1M11 10h1M11 13h1M11 16h1" />
		</>
	),
	grid: (
		<>
			<rect x="4" y="4" width="7" height="7" rx="1.5" />
			<rect x="13" y="4" width="7" height="7" rx="1.5" />
			<rect x="4" y="13" width="7" height="7" rx="1.5" />
			<rect x="13" y="13" width="7" height="7" rx="1.5" />
		</>
	),
	refresh: <path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7" />,
	home: <path d="M4 11l8-7 8 7v9h-5v-6H9v6H4v-9Z" />,
	sliders: (
		<>
			<path d="M4 7h10M18 7h2M4 17h4M12 17h8" />
			<circle cx="16" cy="7" r="2" />
			<circle cx="10" cy="17" r="2" />
		</>
	),
	dots: (
		<>
			<circle cx="12" cy="5" r="1.2" />
			<circle cx="12" cy="12" r="1.2" />
			<circle cx="12" cy="19" r="1.2" />
		</>
	),
	download: <path d="M12 4v12M7 11l5 5 5-5M5 20h14" />,
	restore: (
		<>
			<path d="M12 20V8M7 13l5-5 5 5" />
			<path d="M5 4h14" />
		</>
	)
};

export default function WizardIcon({ name, size = 18, strokeWidth = 1.8, className = '' }) {
	if (!PATHS[name]) return null;
	return (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth={strokeWidth}
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			className={className}
		>
			{PATHS[name]}
		</svg>
	);
}
