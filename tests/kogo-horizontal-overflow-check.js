// Run in the browser console on exhibition listings, single exhibitions, and home.
// Check mobile, laptop, and large-screen widths; carousel tracks may overflow internally.
(() => {
	const viewport = document.documentElement.clientWidth;
	const width = document.documentElement.scrollWidth;
	if (width > viewport + 1) {
		throw new Error(`Horizontal page overflow: ${width}px content in a ${viewport}px viewport.`);
	}
	return { path: location.pathname, viewport, width, passed: true };
})();
