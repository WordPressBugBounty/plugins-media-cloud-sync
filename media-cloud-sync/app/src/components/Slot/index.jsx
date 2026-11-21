import React from 'react';
import { applyFilters } from '../../helper/extend';

// Renders the components an add-on registers on `wpmcs.slot.{name}`; children when none.
export default function Slot({ name, children = null, ...props }) {
	const items = React.useMemo(() => applyFilters(`wpmcs.slot.${name}`, []), [name]);

	if (!items?.length) return children;

	return items.map((Item, index) => <Item key={index} {...props} />);
}
