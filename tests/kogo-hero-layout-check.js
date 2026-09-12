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
	const announcement = document.querySelector('.kogo-announcement:not([hidden])');
	if (announcement && document.body.classList.contains('home') && window.scrollY === 0) {
		if (rect.bottom > document.documentElement.clientHeight + 1) {
			throw new Error('The homepage hero must fit below the visible announcement and header.');
		}
		close(+getComputedStyle(hero).getPropertyValue('--kogo-announcement-height').replace('px', ''), announcement.getBoundingClientRect().height, 'Reserved announcement height');
		if (window.innerWidth <= 1024 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			if (getComputedStyle(announcement.querySelector('.kogo-announcement__track')).animationName !== 'kogo-announcement-marquee') {
				throw new Error('The announcement marquee must run whenever the compact menu is used.');
			}
		}
		if (window.innerWidth <= 1024) {
			const headerItems = Array.from(document.querySelector('.kogo-header').children);
			const centers = headerItems.map(item => { const bounds = item.getBoundingClientRect(); return bounds.top + bounds.height / 2; });
			for (const center of centers) close(center, centers[0], 'Compact header items must share one centered row');
		}
	}
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
