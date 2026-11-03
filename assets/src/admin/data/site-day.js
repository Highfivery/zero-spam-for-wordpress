/**
 * The day of a REST date on this page's site, in the one date pattern.
 */

/**
 * Internal dependencies
 */
import { boot, nowLocal } from './api';
import { day } from './format';
import { siteWall } from './site-time';

/**
 * A date from the API as its day in the site's time zone: an absolute time
 * (a block's start or end, ending in Z) in the zone's offset for that date; a
 * date without a zone is already site time (date_added).
 *
 * @param {string|null} value Date.
 * @return {string} "Thu, Oct 22", or ''.
 */
export function siteDay( value ) {
	const wall = siteWall( value, boot.timezone );
	return wall ? day( wall, new Date( nowLocal() ) ) : '';
}
