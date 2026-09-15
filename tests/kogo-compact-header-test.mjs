import assert from 'node:assert/strict';
import { setupCompactHeader } from '../themes/kogo/assets/compact-header.mjs';

function element(rect = {}) {
	const classes = new Set();
	return {
		attributes: {}, children: [], listeners: {}, properties: {},
		classList: {
			add: name => classes.add(name), remove: name => classes.delete(name),
			contains: name => classes.has(name), replace: (old, name) => { classes.delete(old); classes.add(name); },
			toggle: (name, active) => active ? classes.add(name) : classes.delete(name),
		},
		style: { setProperty(name, value) { this.owner.properties[name] = value; } },
		getBoundingClientRect: () => rect,
		getClientRects: () => [rect],
		focus() { document.activeElement = this; },
		setAttribute(name, value) { this.attributes[name] = value; },
		removeAttribute(name) { delete this.attributes[name]; },
		addEventListener(name, callback) { this.listeners[name] = callback; },
		append(child) { this.children.push(child); child.parentElement = this; },
		prepend(child) { this.children.unshift(child); child.parentElement = this; },
		insertBefore(child) { this.append(child); },
		cloneNode() { const copy = element(rect); copy.attributes = { ...this.attributes }; return copy; },
	};
}

const header = element({ bottom: 106 });
header.style.owner = header;
const toggle = element({ top: 56, left: 720, width: 44, height: 44 });
const logo = element({ top: 56, left: 40, width: 100, height: 44 });
const languages = element();
const utilities = element({ top: 56, left: 556 });
const actions = element();
actions.append(utilities);
languages.attributes = { 'aria-label': 'Keel', lang: 'et', href: '/et/' };
const menu = element();
menu.id = 'modal-1';
const dialog = element();
const content = element();
const items = Array.from({ length: 6 }, () => element());
content.querySelectorAll = () => items;
const close = element();
const clickEvent = () => ({
	prevented: false, stopped: false,
	preventDefault() { this.prevented = true; },
	stopImmediatePropagation() { this.stopped = true; },
});
close.click = () => {
	const event = clickEvent();
	close.listeners.click?.(event);
	if (!event.stopped) { menu.classList.remove('is-menu-open'); mutation(); }
};
header.querySelector = selector => ({
	'.wp-block-navigation__responsive-container-open': toggle,
	'.wp-block-navigation__responsive-container': menu,
	'.kogo-header__logo': logo,
	'.kogo-header__languages': languages,
	'.kogo-header__utilities': utilities,
})[selector];
menu.querySelector = selector => ({
	'.wp-block-navigation__responsive-dialog': dialog,
	'.wp-block-navigation__responsive-container-content': content,
	'.wp-block-navigation__responsive-container-close': close,
})[selector];
let mutation, resize, observeResize, display = 'inline-block';
const motion = { matches: false };
globalThis.document = { createElement: () => element() };
globalThis.window = { matchMedia: () => motion, addEventListener: (name, callback) => { if (name === 'resize') resize = callback; } };
globalThis.getComputedStyle = (element) => ({ display, visibility: 'visible', clipPath: 'none', opacity: '1', transform: 'none', ...element.styleState });
globalThis.MutationObserver = class { constructor(callback) { mutation = callback; } observe() {} };
globalThis.ResizeObserver = class { constructor(callback) { observeResize = callback; } observe() {} };

assert.doesNotThrow(() => setupCompactHeader(null));
setupCompactHeader(header);
assert.equal(toggle.children.length, 3, 'Three decorative lines animate the native trigger');
assert.equal(toggle.attributes['aria-controls'], 'modal-1');
assert.equal(toggle.attributes['aria-expanded'], 'false');
assert.equal(dialog.children[0].classList.contains('kogo-header__menu-logo'), true);
assert.deepEqual(content.children[0].attributes, languages.attributes, 'Preserve translated language labels and links');
assert.equal(content.children[0].classList.contains('kogo-header__languages--menu'), true);
assert.equal(header.properties['--kogo-menu-logo-left'], '40px');
assert.equal(header.properties['--kogo-menu-toggle-left'], '720px');
assert.equal(header.properties['--kogo-menu-content-top'], '106px');
toggle.listeners.click();
menu.classList.add('is-menu-open');
mutation();
assert.equal(header.classList.contains('is-compact-menu-open'), true);
assert.equal(toggle.attributes['aria-hidden'], 'true');
assert.equal(toggle.attributes.tabindex, '-1');
assert.equal(toggle.attributes['aria-expanded'], 'true');
assert.equal(utilities.parentElement, dialog, 'Keep the real search/cart/visibility controls inside the open dialog');
const first = dialog.children[0];
const last = content.children[0];
const hidden = element();
hidden.getClientRects = () => [];
menu.querySelectorAll = () => [first, toggle, last, hidden];
let prevented = false, stopped = false;
const key = (shiftKey) => ({ key: 'Tab', shiftKey, preventDefault() { prevented = true; }, stopPropagation() { stopped = true; } });
document.activeElement = last;
menu.listeners.keydown(key(false));
assert.equal(document.activeElement, first, 'Tab from the last language wraps to the menu logo');
assert.equal(prevented && stopped, true);
menu.listeners.keydown(key(true));
assert.equal(document.activeElement, last, 'Shift+Tab from the menu logo wraps to the last language');
document.activeElement = toggle;
prevented = false;
menu.listeners.keydown(key(false));
assert.equal(prevented, false, 'Interior Tab keeps normal browser order');
stopped = false;
menu.listeners.keydown({ ...key(false), key: 'Escape' });
assert.equal(stopped, false, 'Escape stays with WordPress');
observeResize();
resize();
assert.equal(menu.classList.contains('is-menu-open'), true, 'Crossing the old mobile breakpoint must not close the tablet menu');
display = 'none';
resize();
assert.equal(menu.classList.contains('is-menu-open'), false, 'Returning to desktop navigation closes the native overlay');
assert.equal(toggle.attributes['aria-expanded'], 'false');
assert.equal(toggle.attributes['aria-hidden'], undefined);
assert.equal(toggle.attributes.tabindex, undefined);
assert.equal(utilities.parentElement, actions, 'Restore the real controls to the closed header');
menu.classList.add('is-menu-open');
mutation();
utilities.listeners.click();
assert.equal(menu.classList.contains('is-menu-open'), false, 'Using a site tool closes the menu before opening its panel');
assert.equal(utilities.parentElement, actions);

const effects = [];
const surface = menu.children[0];
assert.equal(surface.className, 'kogo-header__menu-surface');
for (const target of [surface, ...items]) {
	target.animate = (frames, options) => {
		let finish, reject;
		const animation = {
			finished: new Promise((resolve, fail) => { finish = resolve; reject = fail; }),
			cancelled: false,
			cancel() { this.cancelled = true; reject(Error('Cancelled')); },
			finish() { finish(); },
		};
		effects.push({ target, frames, options, animation });
		return animation;
	};
}
const settle = async () => { await Promise.resolve(); await Promise.resolve(); await Promise.resolve(); };
display = 'inline-block';
menu.classList.add('is-menu-open');
mutation();
assert.equal(effects.length, 7, 'One panel reveal plus six staggered rows');
assert.equal(effects[0].options.duration, 460);
assert.equal(effects[0].frames[0].clipPath, 'inset(0 0 calc(100% - 106px) 0)', 'Keep the header inside the wipe');
assert.deepEqual(effects.slice(1).map(effect => effect.options.delay), [70, 112, 154, 196, 238, 280]);

// Interrupt the entrance and ensure the exit starts from the current frame.
items[0].styleState = { opacity: '0.4', transform: 'matrix(1, 0, 0, 1, 0, 12)' };
close.click();
assert.equal(menu.classList.contains('is-menu-open'), true, 'Retain native dialog/scroll lock until the exit finishes');
assert.equal(header.classList.contains('is-compact-menu-closing'), true);
assert.equal(effects[7].options.duration + effects[7].options.delay, 260);
assert.equal(effects[8].frames[0].opacity, '0.4');
assert.equal(effects[8].frames[0].transform, 'matrix(1, 0, 0, 1, 0, 12)');
assert.deepEqual(effects.slice(8).map(effect => effect.options.delay), [90, 72, 54, 36, 18, 0]);
assert.ok(effects.slice(0, 7).every(effect => effect.animation.cancelled));
close.click();
assert.equal(effects.length, 14, 'Repeated close clicks must not restart the exit');
effects.slice(7).forEach(effect => effect.animation.finish());
await settle();
assert.equal(menu.classList.contains('is-menu-open'), false);
assert.equal(header.classList.contains('is-compact-menu-closing'), false);
assert.equal(utilities.parentElement, actions);

motion.matches = true;
menu.classList.add('is-menu-open');
mutation();
assert.equal(effects.length, 14, 'Reduced motion skips the panel and item choreography');
close.click();
assert.equal(menu.classList.contains('is-menu-open'), false, 'Reduced motion closes without a delay');
motion.matches = false;
menu.classList.add('is-menu-open');
mutation();
utilities.listeners.click();
assert.equal(menu.classList.contains('is-menu-open'), false, 'Tools bypass the animation and keep their immediate behavior');
await settle();
console.log('Kogo compact header test passed.');
