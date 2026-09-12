import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../themes/kogo/assets/visibility.js', import.meta.url), 'utf8');
const key = 'kogo-visibility-settings';
const defaults = { textSize: 'medium', lineSpacing: '2', contrast: 'regular' };
const preferred = { textSize: 'very-large', lineSpacing: '6', contrast: 'high' };
const event = () => ({ preventDefault() {} });

function load(value = null, { blocked = false, headerless = false } = {}) {
	const element = () => ({
		listeners: {}, attributes: {},
		addEventListener(name, callback) { this.listeners[name] = callback; },
		setAttribute(name, value) { this.attributes[name] = value; },
		focus() { this.focused = true; },
	});
	const radios = Object.entries({ textSize: ['medium', 'large', 'very-large'], lineSpacing: ['2', '4', '6'], contrast: ['regular', 'high'] })
		.flatMap(([name, values]) => values.map(value => ({ ...element(), name, value })));
	const form = element();
	form.querySelectorAll = () => radios;
	form.querySelector = () => radios.find(input => input.checked);
	const toggle = element();
	const close = element();
	const status = {};
	const panel = { ...element(), id: 'kogo-visibility', hidden: true, scrollIntoView() {} };
	panel.querySelector = selector => ({ form, '.kogo-visibility__close': close, '[data-visibility-status]': status })[selector];
	const document = {
		...element(), documentElement: { dataset: {} },
		querySelector: () => headerless ? null : panel,
		querySelectorAll: () => headerless ? [] : [toggle],
	};
	const window = element();
	let stored = value;
	runInNewContext(source, {
		document, window,
		localStorage: {
			getItem() { if (blocked) throw Error('Storage blocked'); return stored; },
			setItem(name, value) { assert.equal(name, key); if (blocked) throw Error('Storage blocked'); stored = value; },
		},
		FormData: function () { return radios.filter(input => input.checked).map(input => [input.name, input.value]); },
	});
	const applied = () => ({
		textSize: document.documentElement.dataset.kogoTextSize,
		lineSpacing: document.documentElement.dataset.kogoLineSpacing,
		contrast: document.documentElement.dataset.kogoContrast,
	});
	// Preferences must apply before DOMContentLoaded, to avoid a flash on each new page.
	const beforeReady = applied();
	document.listeners.DOMContentLoaded();
	return { document, window, panel, form, toggle, close, status, radios, applied, beforeReady, stored: () => stored };
}

const site = load();
assert.deepEqual(site.applied(), defaults);
assert.equal(site.panel.hidden, true);
site.toggle.listeners.click();
assert.equal(site.panel.hidden, false);
assert.equal(site.toggle.attributes['aria-expanded'], 'true');
site.radios.forEach(input => { input.checked = input.value === preferred[input.name]; });
assert.deepEqual(site.applied(), defaults, 'Selecting a draft must not apply it');
site.close.listeners.click();
assert.equal(site.panel.hidden, true);
assert.equal(site.toggle.focused, true);
site.toggle.listeners.click();
assert.ok(site.radios.filter(input => input.checked).every(input => input.value === defaults[input.name]), 'Reopening discards unapplied choices');
site.radios.forEach(input => { input.checked = input.value === preferred[input.name]; });
site.form.listeners.submit(event());
assert.deepEqual(site.applied(), preferred);
assert.equal(site.panel.hidden, true);
assert.deepEqual(JSON.parse(site.stored()), preferred);

const nextPage = load(site.stored());
assert.deepEqual(nextPage.beforeReady, preferred, 'Saved preferences apply before page content is ready');
assert.deepEqual(nextPage.applied(), preferred, 'Navigation and reload restore the saved settings');
nextPage.toggle.listeners.click();
nextPage.document.listeners.keydown({ ...event(), key: 'Escape' });
assert.equal(nextPage.panel.hidden, true);
nextPage.toggle.listeners.click();
nextPage.toggle.listeners.click();
assert.equal(nextPage.panel.hidden, true, 'Eye toggles open and closed');
nextPage.form.listeners.reset(event());
assert.deepEqual(nextPage.applied(), defaults);
assert.deepEqual(load(nextPage.stored()).applied(), defaults, 'Reset persists across pages');
nextPage.window.listeners.storage({ key, newValue: JSON.stringify(preferred) });
assert.deepEqual(nextPage.applied(), preferred, 'Other tabs adopt preference changes');
nextPage.window.listeners.storage({ key: null, newValue: null });
assert.deepEqual(nextPage.applied(), defaults, 'Clearing storage restores defaults');

for (const corrupt of ['{', 'null', '[]', '{"textSize":"invalid","lineSpacing":6,"contrast":"yellow"}']) {
	assert.deepEqual(load(corrupt).applied(), defaults);
}
assert.deepEqual(load(JSON.stringify(preferred), { headerless: true }).applied(), preferred);
const blocked = load(null, { blocked: true });
blocked.radios.forEach(input => { input.checked = input.value === preferred[input.name]; });
blocked.form.listeners.submit(event());
assert.deepEqual(blocked.applied(), preferred, 'Unavailable storage must not prevent settings from working');
assert.match(blocked.status.textContent, /could not save/);

console.log('Kogo visibility preferences test passed.');
