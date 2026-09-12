import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { setImmediate } from 'node:timers/promises';

const source = readFileSync(new URL('../themes/kogo/assets/main.js', import.meta.url), 'utf8');
const setup = source.slice(source.indexOf('\tconst setupSiteSearch = () => {'), source.indexOf('\n\tsetupSiteSearch();'));
const headerStyles = readFileSync(new URL('../themes/kogo/assets/_header.scss', import.meta.url), 'utf8');
const stickyHeader = headerStyles.slice(0, headerStyles.indexOf('\n.kogo-header {'));
assert.match(stickyHeader, /background: transparent;/, 'The sticky header must not paint white over the collapsing search area');
assert.match(stickyHeader, /height: var\(--kogo-header-height\);/, 'White header paint must be bounded to the navigation row height');

const createSearch = ({ reduced = false, searchPage = false } = {}) => {
	const animations = [];
	const makeElement = () => ({
		listeners: {},
		attributes: {},
		style: { clipPath: 'none', opacity: '1', transform: 'none' },
		addEventListener(name, listener) { this.listeners[name] = listener; },
		setAttribute(name, value) { this.attributes[name] = value; },
		removeAttribute(name) { delete this.attributes[name]; },
		focus(options) { this.focusOptions = options; this.focused = true; },
		animate(frames, timing) {
			let resolve;
			let reject;
			const element = this;
			const animation = {
				frames,
				timing,
				finished: new Promise((done, fail) => { resolve = done; reject = fail; }),
				cancel() { this.cancelled = true; element.style = { clipPath: 'none', opacity: '1', transform: 'none' }; reject(); },
				finish() { resolve(); },
			};
			animations.push(animation);
			return animation;
		},
	});
	const toggle = makeElement();
	const panel = makeElement();
	const form = makeElement();
	const input = makeElement();
	const close = makeElement();
	const main = makeElement();
	const footer = makeElement();
	const pageInput = searchPage ? makeElement() : null;
	panel.hidden = true;
	panel.querySelector = (selector) => ({ 'input[type="search"]': input, '.kogo-search-panel__close': close, form })[selector];
	panel.getBoundingClientRect = () => ({ height: 124 });
	panel.closest = () => ({ parentElement: { children: [main, footer] } });
	main.matches = footer.matches = () => true;
	const keyListeners = {};
	const motion = { matches: reduced };
	const document = {
		querySelector: (selector) => ({
			'.kogo-header__icon-button--search': toggle,
			'#kogo-site-search': panel,
			'#kogo-search-page': pageInput && { querySelector: () => pageInput },
		})[selector],
		addEventListener: (name, listener) => { keyListeners[name] = listener; },
	};
	runInNewContext(`${setup}\nsetupSiteSearch();`, {
		document,
		window: { location: { search: '?s=Floral' }, matchMedia: () => motion, getComputedStyle: (element) => element.style },
		URLSearchParams,
	});
	return { toggle, panel, input, form, close, main, pageInput, animations, keyListeners, motion };
};

const search = createSearch();
assert.equal(search.panel.hidden, true, 'Other pages start with search closed');
search.toggle.listeners.click();
assert.equal(search.toggle.attributes['aria-expanded'], 'true');
assert.equal(search.panel.hidden, false);
assert.equal(search.panel.inert, false);
assert.equal(search.input.focusOptions.preventScroll, true);
assert.equal(search.animations.length, 4, 'Panel, field, main and footer animate together');
assert.equal(search.animations[0].timing.duration, 380);
assert.equal(search.animations[2].frames[0].transform, 'translateY(-124px)');
search.animations.slice(0, 4).forEach((animation) => animation.finish());
await setImmediate();
assert.equal(search.panel.hidden, false);
assert.ok(search.animations.every((animation) => animation.cancelled), 'Idle pages retain no animation transforms');

search.close.listeners.click();
assert.equal(search.panel.hidden, false, 'Closing keeps the panel visible until its animation finishes');
assert.equal(search.panel.inert, true, 'A closing panel cannot receive focus');
assert.equal(search.toggle.attributes['aria-expanded'], 'false');
assert.equal(search.toggle.focusOptions.preventScroll, true);
assert.equal(search.animations[4].timing.duration, 260);
assert.equal(search.animations[4].frames[0].clipPath, 'inset(0 0 0% 0)');
search.animations.slice(4, 8).forEach((animation) => animation.finish());
await setImmediate();
assert.equal(search.panel.hidden, true);

search.toggle.listeners.click();
search.panel.style.clipPath = 'inset(0 0 46% 0)';
search.form.style.opacity = '0.54';
search.main.style.transform = 'matrix(1, 0, 0, 1, 0, -57)';
search.keyListeners.keydown({ key: 'Escape' });
assert.equal(search.animations[12].frames[0].clipPath, 'inset(0 0 46% 0)', 'Interruptions start from the current visual state');
assert.equal(search.animations[13].frames[0].opacity, '0.54');
assert.equal(search.animations[14].frames[0].transform, 'matrix(1, 0, 0, 1, 0, -57)');
search.animations.slice(8, 12).forEach((animation) => animation.finish());
await setImmediate();
assert.equal(search.panel.hidden, false, 'An obsolete animation must not hide the current panel');
search.toggle.listeners.click();
search.animations.slice(16, 20).forEach((animation) => animation.finish());
await setImmediate();
assert.equal(search.panel.hidden, false, 'Rapid toggles settle in the requested state');
assert.equal(search.toggle.attributes['aria-expanded'], 'true');

search.motion.matches = true;
search.close.listeners.click();
assert.equal(search.panel.hidden, true);
assert.equal(search.animations.length, 20, 'Reduced motion updates state immediately without animations');
search.toggle.listeners.click();
assert.equal(search.panel.hidden, false);
assert.equal(search.animations.length, 20);

const page = createSearch({ searchPage: true });
page.toggle.listeners.click();
assert.equal(page.pageInput.value, 'Floral');
assert.equal(page.pageInput.focused, true);
assert.equal(page.panel.hidden, true);
assert.equal(page.toggle.attributes['aria-controls'], 'kogo-search-page');
assert.equal(page.animations.length, 0, 'Search results keep their permanent field unchanged');

console.log('Kogo site search animation test passed.');
