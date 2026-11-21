// Add-on extension API, over core's wp.hooks (script dependency 'wp-hooks').
const hooks = () => window.wp?.hooks;

export const applyFilters = (name, value, ...args) =>
	hooks() ? hooks().applyFilters(name, value, ...args) : value;

export const doAction = (name, ...args) => {
	if (hooks()) hooks().doAction(name, ...args);
};
