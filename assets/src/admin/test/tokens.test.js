/**
 * Guards the design tokens (styles/tokens.css) and the scoping of the shared
 * stylesheets:
 *
 * - every text colour meets 4.5:1 and every UI colour 3:1 against the surfaces
 *   it sits on, in the light and the dark theme (WCAG 2.2 1.4.3 and 1.4.11);
 * - the explicit dark theme and the "auto" dark theme declare the same values;
 * - no selector reaches outside the Zero Spam roots.
 */

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const stylesDir = join(__dirname, '..', 'styles');
const srcDir = join(__dirname, '..', '..');

const read = (file) => readFileSync(file, 'utf8');

/**
 * Splits a stylesheet into its rules, one level of at-rule nesting deep.
 *
 * @param {string} css Stylesheet source.
 * @return {Array<{atRule: string|null, selector: string, body: string}>} Rules.
 */
function parseRules(css) {
	const source = css.replace(/\/\*[\s\S]*?\*\//g, '');
	const rules = [];

	const walk = (text, atRule) => {
		let depth = 0;
		let start = 0;
		let head = '';
		for (let i = 0; i < text.length; i++) {
			const char = text[i];
			if (char === '{') {
				if (depth === 0) {
					head = text.slice(start, i).trim();
					start = i + 1;
				}
				depth++;
			} else if (char === '}') {
				depth--;
				if (depth === 0) {
					const body = text.slice(start, i);
					if (head.startsWith('@')) {
						walk(body, head);
					} else {
						rules.push({
							atRule,
							selector: head.replace(/\s+/g, ' '),
							body,
						});
					}
					start = i + 1;
				}
			} else if (char === ';' && depth === 0) {
				// Statements such as @import.
				start = i + 1;
			}
		}
	};

	walk(source, null);
	return rules;
}

/**
 * @param {string} body Declaration block.
 * @return {Object<string, string>} Custom properties declared in it.
 */
function customProperties(body) {
	const props = {};
	for (const match of body.matchAll(/(--[\w-]+)\s*:\s*([^;]+);?/g)) {
		props[match[1]] = match[2].trim();
	}
	return props;
}

/**
 * Resolves var() references against a token set.
 *
 * @param {Object<string, string>} tokens Token set.
 * @param {string}                 name   Custom property name.
 * @return {string} The resolved value.
 */
function resolve(tokens, name) {
	let value = tokens[name];
	for (let guard = 0; value && value.startsWith('var('); guard++) {
		if (guard > 10) {
			throw new Error(`${name} has a var() cycle`);
		}
		value = tokens[value.slice(4, -1).trim()];
	}
	if (value === undefined) {
		throw new Error(`${name} is not defined`);
	}
	return value;
}

/**
 * Relative luminance of a #rgb or #rrggbb colour (WCAG 2.2 definition).
 *
 * @param {string} hex Colour.
 * @return {number} Luminance, 0 to 1.
 */
function luminance(hex) {
	let digits = hex.replace('#', '');
	if (digits.length === 3) {
		digits = [...digits].map((d) => d + d).join('');
	}
	if (!/^[0-9a-f]{6}$/i.test(digits)) {
		throw new Error(`${hex} is not a solid hex colour`);
	}
	const [r, g, b] = [0, 2, 4].map((i) => {
		const channel = parseInt(digits.slice(i, i + 2), 16) / 255;
		return channel <= 0.04045
			? channel / 12.92
			: ((channel + 0.055) / 1.055) ** 2.4;
	});
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * @param {string} a Colour.
 * @param {string} b Colour.
 * @return {number} WCAG contrast ratio, 1 to 21.
 */
function contrast(a, b) {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
	return (hi + 0.05) / (lo + 0.05);
}

const rules = parseRules(read(join(stylesDir, 'tokens.css')));

const lightRule = rules.find(
	(rule) =>
		rule.atRule === null &&
		rule.selector === '.zs-app, .zs-blocked, .zs-widget'
);
const darkRule = rules.find(
	(rule) =>
		rule.atRule === null && rule.selector.includes('[data-zs-theme="dark"]')
);
const autoRule = rules.find(
	(rule) =>
		/prefers-color-scheme:\s*dark/.test(rule.atRule || '') &&
		rule.selector.includes('[data-zs-theme="auto"]')
);

const light = customProperties(lightRule?.body || '');
const dark = { ...light, ...customProperties(darkRule?.body || '') };

const surfaces = [
	'bg',
	'surface',
	'surface-2',
	'surface-3',
	'sunken',
	'nav-hover',
	'ni-tint',
];

// Text: 4.5:1 (WCAG 1.4.3).
const textPairs = [
	...['ink', 'ink-2', 'ink-3', 'eyebrow', 'sx-label', 'red'].flatMap((fg) =>
		surfaces.map((bg) => [fg, bg])
	),
	['on-action', 'action'],
	['on-action', 'action-hover'],
	['red-soft-ink', 'red-soft'],
	['ok-ink', 'ok-soft'],
	['warn-ink', 'warn-soft'],
	['info', 'info-soft'],
	['enh-ink', 'enh-bg'],
];

// Controls, focus ring, status icons and chart strokes: 3:1 (WCAG 1.4.11).
const uiPairs = ['control', 'focus', 'action', 'red', 'ok', 'warn', 'spark']
	.flatMap((fg) =>
		['bg', 'surface', 'surface-2', 'sunken'].map((bg) => [fg, bg])
	)
	.concat([['focus-on-dark', 'enh-bg']]);

describe('design tokens', () => {
	it('has a light block, a dark block and an auto dark block', () => {
		expect(lightRule).toBeDefined();
		expect(darkRule).toBeDefined();
		expect(autoRule).toBeDefined();
	});

	it('declares the same values for dark and for auto in a dark OS', () => {
		expect(customProperties(autoRule.body)).toEqual(
			customProperties(darkRule.body)
		);
	});

	it('only overrides tokens in dark that the light theme defines', () => {
		const unknown = Object.keys(customProperties(darkRule.body)).filter(
			(name) => !(name in light)
		);
		expect(unknown).toEqual([]);
	});

	describe.each([
		['light', light],
		['dark', dark],
	])('%s theme', (theme, tokens) => {
		it.each(textPairs)(
			'text --zs-%s on --zs-%s is at least 4.5:1',
			(fg, bg) => {
				const ratio = contrast(
					resolve(tokens, `--zs-${fg}`),
					resolve(tokens, `--zs-${bg}`)
				);
				expect(ratio).toBeGreaterThanOrEqual(4.5);
			}
		);

		it.each(uiPairs)('UI --zs-%s on --zs-%s is at least 3:1', (fg, bg) => {
			const ratio = contrast(
				resolve(tokens, `--zs-${fg}`),
				resolve(tokens, `--zs-${bg}`)
			);
			expect(ratio).toBeGreaterThanOrEqual(3);
		});
	});
});

describe('stylesheet scoping', () => {
	const files = [
		join(stylesDir, 'tokens.css'),
		join(stylesDir, 'base.css'),
		join(stylesDir, 'logo.css'),
		join(srcDir, 'widget-refresh', 'index.css'),
		join(srcDir, 'blocked', 'index.css'),
	];

	it.each(files)(
		'every selector in %s stays inside Zero Spam markup',
		(file) => {
			const leaking = parseRules(read(file))
				.filter((rule) => !/@keyframes/.test(rule.atRule || ''))
				.flatMap((rule) =>
					rule.selector
						.split(/,(?![^(]*\))/)
						.map((selector) => selector.trim())
				)
				.filter(
					(selector) =>
						!/\.zs-[\w-]+|\[data-zs-[\w-]+/.test(selector) ||
						/(^|[\s>+~(,])(:root|html|body)\b/.test(selector)
				);
			expect(leaking).toEqual([]);
		}
	);

	it('prefixes every keyframes name with zs-', () => {
		const names = files.flatMap((file) =>
			[...read(file).matchAll(/@keyframes\s+([\w-]+)/g)].map(
				(match) => match[1]
			)
		);
		expect(names.filter((name) => !name.startsWith('zs-'))).toEqual([]);
	});
});
