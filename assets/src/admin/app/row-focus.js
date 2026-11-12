/**
 * Keeps focus on "the same control" when a list switches between a table
 * and cards (Rules at 1,440px, Activity at 1,200px): controls carry
 * `data-zs-ctl` (and rows `data-zs-row`), so the focused one can be noted
 * before the switch and its twin found after it.
 */

/**
 * The list control an element is, to find its match after a switch.
 *
 * @param {Element|null}      root    The list's root.
 * @param {Element|null|void} element Focused element.
 * @return {{row: string|null, control: string}|null} Row (none for the list's own controls) and control.
 */
export function rowControlOf( root, element ) {
	const control = element?.closest?.( '[data-zs-ctl]' );
	if ( ! root || ! control || ! root.contains( control ) ) {
		return null;
	}
	return {
		row: control.closest( '[data-zs-row]' )?.dataset.zsRow ?? null,
		control: control.dataset.zsCtl,
	};
}

/**
 * Finds the control that matches a noted one in the current layout.
 *
 * @param {Element|null}                        root    The list's root.
 * @param {{row: string|null, control: string}} noted   From rowControlOf().
 * @param {Object<string, string[]>}            targets What a control becomes in the other layout; the first one present wins.
 * @return {Element|null} The control.
 */
export function findControl( root, { row, control }, targets = {} ) {
	const scope =
		null === row ? root : root?.querySelector( `[data-zs-row="${ row }"]` );
	for ( const name of targets[ control ] || [ control ] ) {
		const found = scope?.querySelector( `[data-zs-ctl="${ name }"]` );
		if ( found ) {
			return found;
		}
	}
	return null;
}
