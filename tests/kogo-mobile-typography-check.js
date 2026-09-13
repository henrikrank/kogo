// Run in the browser console at widths up to 782px, including enlarged-text mode.
(() => {
	if (window.innerWidth > 782) throw new Error('Use a mobile viewport first.');
	const rootSize = +getComputedStyle(document.documentElement).fontSize.replace('px', '');
	const limits = {
		'.kogo-emphasized-text__body': 1.75,
		'.kogo-posts-slider__heading': 2.25,
		'.kogo-artists__heading': 2.25,
		'.kogo-post-highlight__title': 1.25,
		'.kogo-hero-slider__title': 3,
		'.kogo-footer__title': 2.25,
	};
	for (const [selector, maxRem] of Object.entries(limits)) {
		for (const element of document.querySelectorAll(selector)) {
			const size = +getComputedStyle(element).fontSize.replace('px', '');
			if (size > rootSize * maxRem + 1) {
				throw new Error(`${selector} exceeds the compact display scale: ${size}px.`);
			}
		}
	}
	const bodySize = +getComputedStyle(document.body).fontSize.replace('px', '');
	if (bodySize < rootSize) throw new Error('Body text must not shrink below the root size.');
	return { viewport: window.innerWidth, rootSize, bodySize, passed: true };
})();
