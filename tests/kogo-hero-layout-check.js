// Run in the browser console on the homepage or a single exhibition page.
// Check laptop/mobile widths below 1920px and the unchanged large-screen layout.
(() => {
	const hero = document.querySelector('.kogo-hero-slider, .kogo-exhibition-single__hero');
	if (!hero) throw new Error('Open the homepage or a single exhibition first.');
	const rect = hero.getBoundingClientRect();
	const viewport = document.documentElement.clientWidth;
	const close = (actual, expected, message) => {
		if (Math.abs(actual - expected) > 1) {
			throw new Error(`${message}: expected ${expected}px, got ${actual}px`);
		}
	};
	if (window.innerWidth < 1920) {
		close(rect.left, 4, 'Hero left gutter');
		close(viewport - rect.right, 4, 'Hero right gutter');
	} else {
		close(rect.width, 1366, 'Large-screen hero width');
		close(rect.left, (viewport - rect.width) / 2, 'Large-screen centering');
	}
	const title = hero.querySelector('.kogo-exhibition-single__title');
	if (title) {
		const inner = hero.querySelector('.kogo-exhibition-single__hero-inner');
		const padding = +getComputedStyle(inner).paddingBottom.replace('px', '');
		close(rect.bottom - title.getBoundingClientRect().bottom, padding, 'Exhibition title bottom inset');
		for (const paragraph of hero.querySelectorAll('p:empty')) {
			if (getComputedStyle(paragraph).display !== 'none') {
				throw new Error('WordPress empty paragraphs must not participate in hero layout.');
			}
		}
	}
	return { viewport: window.innerWidth, left: rect.left, right: viewport - rect.right, width: rect.width, passed: true };
})();
