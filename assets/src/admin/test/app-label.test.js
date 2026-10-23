/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { getAppLabel } from '../app-label';

describe('getAppLabel', () => {
	it('names the plugin when the version is unknown', () => {
		expect(getAppLabel('')).toBe('Zero Spam');
	});

	it('includes the version when it is known', () => {
		expect(getAppLabel('6.0.0')).toBe('Zero Spam 6.0.0');
	});
});
