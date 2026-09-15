// Retain WordPress's menu actions; enhance the dialog layout and transitions.
export function setupCompactHeader(header) {
	const toggle = header?.querySelector('.wp-block-navigation__responsive-container-open');
	const menu = header?.querySelector('.wp-block-navigation__responsive-container');
	const dialog = menu?.querySelector('.wp-block-navigation__responsive-dialog');
	const content = menu?.querySelector('.wp-block-navigation__responsive-container-content');
	const close = menu?.querySelector('.wp-block-navigation__responsive-container-close');
	const logo = header?.querySelector('.kogo-header__logo');
	const languages = header?.querySelector('.kogo-header__languages');
	const utilities = header?.querySelector('.kogo-header__utilities');
	if (!toggle || !menu || !dialog || !content || !close || !logo || !languages || !utilities) return;
	const actions = utilities.parentElement;
	const surface = document.createElement('div');
	surface.className = 'kogo-header__menu-surface';
	surface.setAttribute('aria-hidden', 'true');
	menu.prepend(surface);
	const items = Array.from(content.querySelectorAll(':scope > .wp-block-navigation__container > .wp-block-navigation-item'));
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
	let animations = [];
	let wasOpen = false;
	let closing = false;
	let allowNativeClose = false;
	const cancelAnimations = () => {
		animations.forEach((animation) => animation.cancel());
		animations = [];
	};
	const animateMenu = (open) => {
		if (reducedMotion.matches || typeof surface.animate !== 'function') return Promise.resolve(true);
		const collapsed = `inset(0 0 calc(100% - ${header.getBoundingClientRect().bottom}px) 0)`;
		const styles = [surface, ...items].map((element) => getComputedStyle(element));
		// Capture interrupted frames before cancelling, so quick close clicks don't jump.
		const current = styles.map((style) => ({ clipPath: style.clipPath, opacity: style.opacity, transform: style.transform }));
		cancelAnimations();
		animations = [
			surface.animate([
				{ clipPath: open ? collapsed : current[0].clipPath === 'none' ? 'inset(0 0 0 0)' : current[0].clipPath },
				{ clipPath: open ? 'inset(0 0 0 0)' : collapsed },
			], { duration: open ? 460 : 190, delay: open ? 0 : 70, easing: 'cubic-bezier(0.16, 1, 0.3, 1)', fill: 'both' }),
			...items.map((item, index) => item.animate([
				{ opacity: open ? 0 : current[index + 1].opacity, transform: open ? 'translateY(28px)' : current[index + 1].transform },
				{ opacity: open ? 1 : 0, transform: open ? 'translateY(0px)' : 'translateY(-16px)' },
			], {
				duration: open ? 340 : 160,
				delay: open ? 70 + index * 42 : (items.length - index - 1) * 18,
				easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'both',
			})),
		];
		const running = animations;
		return Promise.all(running.map((animation) => animation.finished)).then(() => {
			if (running !== animations) return false;
			if (open) cancelAnimations();
			return true;
		}, () => false);
	};
	const finishClose = () => {
		closing = false;
		allowNativeClose = true;
		close.click();
		allowNativeClose = false;
	};
	const requestClose = (event) => {
		if (allowNativeClose || !menu.classList.contains('is-menu-open') || reducedMotion.matches || typeof surface.animate !== 'function') return;
		event.preventDefault();
		event.stopImmediatePropagation();
		if (closing) return;
		closing = true;
		header.classList.add('is-compact-menu-closing');
		animateMenu(false).then((finished) => {
			if (finished && closing && menu.classList.contains('is-menu-open')) finishClose();
		});
	};

	const menuLogo = logo.cloneNode(true);
	menuLogo.classList.replace('kogo-header__logo', 'kogo-header__menu-logo');
	dialog.prepend(menuLogo);
	const menuLanguages = languages.cloneNode(true);
	menuLanguages.classList.add('kogo-header__languages--menu');
	content.prepend(menuLanguages);
	for (let index = 0; index < 3; index += 1) {
		const line = document.createElement('span');
		line.className = 'kogo-header__menu-line';
		line.setAttribute('aria-hidden', 'true');
		toggle.append(line);
	}
	toggle.setAttribute('aria-controls', menu.id);
	header.classList.add('kogo-header--menu-ready');

	const updateGeometry = () => {
		const logoRect = logo.getBoundingClientRect();
		const toggleRect = toggle.getBoundingClientRect();
		const toolsRect = utilities.getBoundingClientRect();
		for (const [name, value] of Object.entries({
			'logo-left': logoRect.left, 'logo-top': logoRect.top,
			'logo-width': logoRect.width, 'logo-height': logoRect.height,
			'toggle-left': toggleRect.left, 'toggle-top': toggleRect.top,
			'tools-left': toolsRect.left, 'tools-top': toolsRect.top,
			'content-top': header.getBoundingClientRect().bottom,
		})) {
			header.style.setProperty(`--kogo-menu-${name}`, `${value}px`);
		}
	};
	const syncOpen = () => {
		const open = menu.classList.contains('is-menu-open');
		header.classList.toggle('is-compact-menu-open', open);
		toggle.setAttribute('aria-expanded', String(open));
		if (open) {
			// Move the real controls, preserving their listeners and the dialog's focus scope.
			utilities.classList.add('kogo-header__utilities--menu');
			dialog.insertBefore(utilities, close);
			toggle.setAttribute('aria-hidden', 'true');
			toggle.setAttribute('tabindex', '-1');
		} else {
			utilities.classList.remove('kogo-header__utilities--menu');
			actions.prepend(utilities);
			toggle.removeAttribute('aria-hidden');
			toggle.removeAttribute('tabindex');
			header.classList.remove('is-compact-menu-closing');
			closing = false;
			cancelAnimations();
		}
		if (open && !wasOpen) animateMenu(true);
		wasOpen = open;
	};
	toggle.addEventListener('click', updateGeometry, { capture: true });
	close.addEventListener('click', requestClose, { capture: true });
	utilities.addEventListener('click', () => {
		// Tools act immediately; don't leave Search/Visibility hidden behind an exit.
		if (menu.classList.contains('is-menu-open')) finishClose();
	}, { capture: true });
	// WordPress caches focus endpoints before these links are added. Recompute on
	// Tab so the added links and an expanding Info submenu remain inside the trap.
	menu.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			// Preserve native Escape-to-collapse when focus is inside expanded Info.
			const submenu = event.target?.closest('.wp-block-navigation-item.has-child');
			if (!submenu?.querySelector(':scope > button[aria-expanded="true"]')) requestClose(event);
			return;
		}
		if (event.key !== 'Tab' || !menu.classList.contains('is-menu-open')) return;
		const focusable = Array.from(menu.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
			.filter((element) => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
		const first = focusable[0];
		const last = focusable[focusable.length - 1];
		event.stopPropagation();
		if (event.shiftKey && document.activeElement === first || !event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			(event.shiftKey ? last : first)?.focus();
		}
	}, { capture: true });
	new MutationObserver(syncOpen).observe(menu, { attributes: true, attributeFilter: ['class'] });
	new ResizeObserver(() => {
		if (!menu.classList.contains('is-menu-open')) updateGeometry();
	}).observe(header);
	window.addEventListener('resize', () => {
		if (!menu.classList.contains('is-menu-open')) return;
		if (getComputedStyle(toggle).display === 'none' || closing) finishClose();
		else {
			cancelAnimations();
			// Measure the in-flow burger at the new width, then restore its fixed glyph.
			header.classList.remove('is-compact-menu-open');
			utilities.classList.remove('kogo-header__utilities--menu');
			actions.prepend(utilities);
			updateGeometry();
			syncOpen();
		}
	});
	updateGeometry();
	syncOpen();
}
