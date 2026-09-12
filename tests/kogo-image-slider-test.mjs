import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../themes/kogo/assets/main.js', import.meta.url), 'utf8');
const setup = source.slice(source.indexOf('\tconst setupImageSlider ='), source.indexOf('\n\tconst setupGallery ='));
const check = (length, reducedMotion = false) => {
	const slides = Array.from({ length }, () => ({ classList: { add() {} }, inert: false }));
	const elements = [];
	let options;
	const slider = {
		dataset: {}, querySelectorAll: () => slides, setAttribute() {},
		append(...items) { elements.push(...items); },
	};
	runInNewContext(`${setup}\nsetupImageSlider(slider); setupImageSlider(slider);`, {
		slider,
		window: { matchMedia: () => ({ matches: reducedMotion }) },
		document: { createElement: () => ({ setAttribute() {}, querySelector: (selector) => selector }) },
		A11y: {}, Navigation: {},
		Swiper: function (element, config) {
			assert.equal(options, undefined, 'Initialization must be idempotent');
			options = config;
			config.on.init({ slides, activeIndex: 0, realIndex: 0 });
		},
	});
	return { slides, elements, options };
};

assert.equal(check(0).options, undefined);
assert.equal(check(1).elements[0].hidden, true);
const gallery = check(3);
assert.equal(gallery.options.slidesPerView, 1);
assert.equal(gallery.options.rewind, true);
assert.equal(gallery.elements[1].textContent, '1 / 3');
assert.deepEqual(gallery.slides.map((slide) => slide.inert), [false, true, true]);
gallery.options.on.slideChange({ slides: gallery.slides, activeIndex: 1, realIndex: 1 });
assert.equal(gallery.elements[1].textContent, '2 / 3');
assert.deepEqual(gallery.slides.map((slide) => slide.inert), [true, false, true]);
assert.equal(check(3, true).options.speed, 0);
console.log('Kogo image slider test passed.');
