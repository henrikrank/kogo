(function () {
	'use strict';

	const key = 'kogo-visibility-settings';
	const defaults = { textSize: 'medium', lineSpacing: '2', contrast: 'regular' };
	const options = { textSize: ['medium', 'large', 'very-large'], lineSpacing: ['2', '4', '6'], contrast: ['regular', 'high'] };
	const normalize = (value) => Object.fromEntries(Object.entries(defaults).map(([name, fallback]) => [
		name, options[name].includes(value?.[name]) ? value[name] : fallback,
	]));
	const read = (value) => {
		try { return normalize(JSON.parse(value)); } catch (error) { return { ...defaults }; }
	};
	let settings = { ...defaults };
	try { settings = read(localStorage.getItem(key)); } catch (error) { /* Preferences still work when storage is unavailable. */ }
	const apply = () => {
		document.documentElement.dataset.kogoTextSize = settings.textSize;
		document.documentElement.dataset.kogoLineSpacing = settings.lineSpacing;
		document.documentElement.dataset.kogoContrast = settings.contrast;
	};
	apply();

	document.addEventListener('DOMContentLoaded', () => {
		const panel = document.querySelector('#kogo-visibility');
		const toggles = document.querySelectorAll('.kogo-header__icon-button--eye');
		if (!panel || !toggles.length) return;
		const form = panel.querySelector('form');
		const close = panel.querySelector('.kogo-visibility__close');
		const status = panel.querySelector('[data-visibility-status]');
		let opener = toggles[0];
		const sync = () => {
			form.querySelectorAll('input[type="radio"]').forEach((input) => {
				input.checked = input.value === settings[input.name];
			});
		};
		const save = () => {
			apply();
			try {
				localStorage.setItem(key, JSON.stringify(settings));
				status.textContent = 'Visibility settings saved.';
			} catch (error) {
				status.textContent = 'Visibility settings applied. Your browser could not save them.';
			}
		};
		const setOpen = (open) => {
			if (open) sync();
			panel.hidden = !open;
			toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(open)));
			if (open) {
				panel.scrollIntoView({ block: 'start', behavior: 'instant' });
				form.querySelector('input:checked').focus({ preventScroll: true });
			} else {
				opener.focus({ preventScroll: true });
			}
		};
		toggles.forEach((toggle) => {
			toggle.setAttribute('aria-label', 'Visibility settings');
			toggle.setAttribute('aria-controls', panel.id);
			toggle.setAttribute('aria-expanded', 'false');
			toggle.addEventListener('click', () => { opener = toggle; setOpen(panel.hidden); });
		});
		close.addEventListener('click', () => setOpen(false));
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); setOpen(false); }
		});
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			settings = normalize(Object.fromEntries(new FormData(form)));
			save();
			setOpen(false);
		});
		form.addEventListener('reset', (event) => {
			event.preventDefault();
			settings = { ...defaults };
			sync();
			save();
		});
		window.addEventListener('storage', (event) => {
			if (event.key !== key && event.key !== null) return;
			settings = read(event.newValue);
			apply();
			sync();
		});
		sync();
	});
})();
