// The admin UI schema is built server-side (includes/config/ui-schema.php) so add-ons can
// extend it through PHP filters; it's printed as window.wpmcs_schema before this bundle loads.
// License changes reload the page (License.jsx), which re-renders it with add-on sections.

const LIST_SECTIONS = [
	'dashboard_cards',
	'dashboard_pro_cards',
	'configure_progress_bar',
	'routes',
	'nav',
	'deliveryResetKeys',
	'syncMethods'
];

const cache = {};

// Regex rules arrive as { __regex, flags }; PHP encodes empty objects as [].
const hydrate = (value, key = '') => {
	if (Array.isArray(value)) {
		if (key === 'validation' && !value.length) return {};
		return value.map((item) => hydrate(item));
	}
	if (value && typeof value === 'object') {
		if (typeof value.__regex === 'string') {
			return new RegExp(value.__regex, value.flags || '');
		}
		return Object.fromEntries(
			Object.entries(value).map(([k, v]) => [k, hydrate(v, k)])
		);
	}
	return value;
};

export default function getSchema(schema = '') {
	if (!(schema in cache)) {
		const raw = window.wpmcs_schema?.[schema];
		cache[schema] =
			raw === undefined || raw === null
				? LIST_SECTIONS.includes(schema)
					? []
					: {}
				: hydrate(raw);
	}
	return cache[schema];
}
