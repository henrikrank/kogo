import Swiper from 'swiper';
import { A11y, Autoplay, Navigation } from 'swiper/modules';
import 'swiper/css';
import { getGalleryLayout } from './gallery-layout.mjs';

(function () {
	'use strict';

	const setupHeroSlider = (slider) => {
		const slides = slider.querySelectorAll('.swiper-wrapper > .swiper-slide');
		if (!slides.length || slider.dataset.sliderReady === 'true') {
			return;
		}

		slider.dataset.sliderReady = 'true';
		slider.setAttribute('role', 'region');
		slider.setAttribute('aria-label', 'Featured exhibitions');

		const progress = document.createElement('div');
		progress.className = 'kogo-hero-slider__progress';
		progress.setAttribute('aria-hidden', 'true');

		const progressItems = Array.from(slides, () => {
			const item = document.createElement('span');
			item.className = 'kogo-hero-slider__progress-item';
			item.innerHTML = '<span class="kogo-hero-slider__progress-fill"></span>';
			progress.appendChild(item);
			return item;
		});

		const controls = document.createElement('div');
		controls.className = 'kogo-hero-slider__controls';
		controls.innerHTML =
			'<button class="kogo-hero-slider__arrow kogo-hero-slider__arrow--previous" type="button" aria-label="Previous slide"></button>' +
			'<button class="kogo-hero-slider__arrow kogo-hero-slider__arrow--next" type="button" aria-label="Next slide"></button>';
		controls.hidden = slides.length < 2;

		slider.append(progress, controls);

		const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		const hasAutoplay = slides.length > 1 && !reducedMotion;
		let revealTimer;
		const updateProgress = (activeIndex, value = 0) => {
			progressItems.forEach((item, index) => {
				item.classList.toggle('is-active', index === activeIndex);
				item.style.setProperty('--kogo-hero-progress', index === activeIndex ? value : 0);
			});
		};
		const revealSlide = (instance) => {
			const activeSlide = instance.slides[instance.activeIndex];
			const previousSlide = instance.slides[instance.previousIndex];

			window.clearTimeout(revealTimer);
			instance.slides.forEach((slide) => slide.classList.remove('is-leaving', 'is-revealing'));

			if (previousSlide && previousSlide !== activeSlide) {
				previousSlide.classList.add('is-leaving');
			}

			if (activeSlide && !reducedMotion) {
				void activeSlide.offsetWidth;
				activeSlide.classList.add('is-revealing');
			}

			revealTimer = window.setTimeout(() => {
				instance.slides.forEach((slide) => slide.classList.remove('is-leaving'));
			}, 1100);
		};

		new Swiper(slider, {
			modules: [A11y, Autoplay, Navigation],
			speed: 0,
			loop: slides.length > 1,
			allowTouchMove: slides.length > 1,
			watchOverflow: true,
			navigation: {
				prevEl: controls.querySelector('.kogo-hero-slider__arrow--previous'),
				nextEl: controls.querySelector('.kogo-hero-slider__arrow--next'),
			},
			autoplay: hasAutoplay
				? {
						delay: 6000,
						disableOnInteraction: false,
						waitForTransition: false,
					}
				: false,
			a11y: {
				prevSlideMessage: 'Previous slide',
				nextSlideMessage: 'Next slide',
			},
			on: {
				init(instance) {
					revealSlide(instance);
					updateProgress(instance.realIndex, hasAutoplay ? 0 : 1);
				},
				slideChange(instance) {
					revealSlide(instance);
					updateProgress(instance.realIndex, hasAutoplay ? 0 : 1);
				},
				autoplayTimeLeft(instance, timeLeft, percentage) {
					progressItems[instance.realIndex]?.style.setProperty('--kogo-hero-progress', 1 - percentage);
				},
			},
		});
	};

	const setupPostsSlider = (slider) => {
		const wrapper = slider.querySelector('.kogo-posts-slider__items');
		const slides = wrapper ? Array.from(wrapper.children) : [];
		if (!slides.length || slider.dataset.sliderReady === 'true') {
			return;
		}

		slider.dataset.sliderReady = 'true';
		const itemLabel = slider.dataset.sliderItemLabel || 'posts';
		const isWorksSlider = slider.classList.contains('kogo-exhibition-works');
		slider.setAttribute('role', 'region');
		slider.setAttribute(
			'aria-label',
			slider.dataset.sliderLabel || (slider.classList.contains('kogo-related-news') ? 'Related news' : 'Latest posts')
		);
		slides.forEach((slide) => slide.classList.add('swiper-slide'));

		const controls = document.createElement('div');
		controls.className = 'kogo-posts-slider__controls';
		controls.innerHTML =
			`<button class="kogo-posts-slider__arrow kogo-posts-slider__arrow--previous" type="button" aria-label="Previous ${itemLabel}"></button>` +
			`<button class="kogo-posts-slider__arrow kogo-posts-slider__arrow--next" type="button" aria-label="Next ${itemLabel}"></button>`;
		slider.querySelector('.kogo-posts-slider__actions')?.appendChild(controls);

		new Swiper(slider, {
			modules: [A11y, Navigation],
			speed: 550,
			slidesPerView: 'auto',
			spaceBetween: isWorksSlider ? 8 : 16,
			watchOverflow: true,
			breakpoints: {
				600: {
					spaceBetween: isWorksSlider ? 8 : 20,
				},
				900: {
					spaceBetween: isWorksSlider ? 8 : 24,
				},
			},
			navigation: {
				prevEl: controls.querySelector('.kogo-posts-slider__arrow--previous'),
				nextEl: controls.querySelector('.kogo-posts-slider__arrow--next'),
			},
			a11y: {
				prevSlideMessage: `Previous ${itemLabel}`,
				nextSlideMessage: `Next ${itemLabel}`,
			},
		});
	};

	const setupGallery = (gallery) => {
		const grid = gallery.querySelector('.kogo-gallery-grid');
		const dialog = gallery.querySelector('.kogo-gallery-lightbox');
		const data = gallery.querySelector('.kogo-gallery-data');
		const images = JSON.parse(data?.textContent || '[]');
		if (!grid || !dialog || !images.length) {
			return;
		}

		const lightboxImage = dialog.querySelector('.kogo-gallery-lightbox__image');
		const count = dialog.querySelector('.kogo-gallery-lightbox__count');
		const previous = dialog.querySelector('.kogo-gallery-lightbox__arrow--previous');
		const next = dialog.querySelector('.kogo-gallery-lightbox__arrow--next');
		let current = 0;
		let columns = 0;
		let resizeFrame;

		const showImage = (index) => {
			current = (index + images.length) % images.length;
			lightboxImage.src = images[current].full;
			lightboxImage.alt = images[current].alt;
			count.textContent = `${current + 1} / ${images.length}`;
		};

		const open = (index) => {
			showImage(index);
			dialog.showModal();
		};

		const createTile = (index, moreCount = 0) => {
			const image = images[index];
			const button = document.createElement('button');
			const thumbnail = document.createElement('img');
			button.type = 'button';
			button.className = 'kogo-gallery-grid__item';
			button.setAttribute('aria-label', moreCount ? `View ${moreCount} more images` : image.alt);
			thumbnail.src = image.src;
			thumbnail.alt = '';
			thumbnail.loading = 'lazy';
			thumbnail.decoding = 'async';
			thumbnail.sizes = '(max-width: 599px) 50vw, (max-width: 1024px) 25vw, 17vw';
			if (image.srcset) {
				thumbnail.srcset = image.srcset;
			}
			button.appendChild(thumbnail);
			if (moreCount) {
				button.classList.add('kogo-gallery-grid__item--more');
				const label = document.createElement('span');
				label.textContent = `+${moreCount} more`;
				button.appendChild(label);
			}
			button.addEventListener('click', () => open(index));
			return button;
		};

		const render = () => {
			const nextColumns = getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
			if (nextColumns === columns) {
				return;
			}
			columns = nextColumns;
			const layout = getGalleryLayout(images.length, columns);
			const fragment = document.createDocumentFragment();
			for (let index = 0; index < layout.cells; index += 1) {
				fragment.appendChild(createTile(index, index === layout.moreIndex ? layout.moreCount : 0));
			}
			grid.replaceChildren(fragment);
		};

		previous.addEventListener('click', () => showImage(current - 1));
		next.addEventListener('click', () => showImage(current + 1));
		dialog.querySelector('.kogo-gallery-lightbox__close').addEventListener('click', () => dialog.close());
		dialog.addEventListener('keydown', (event) => {
			if (event.key === 'ArrowLeft') {
				showImage(current - 1);
			} else if (event.key === 'ArrowRight') {
				showImage(current + 1);
			}
		});
		previous.hidden = images.length < 2;
		next.hidden = images.length < 2;
		render();
		window.addEventListener('resize', () => {
			window.cancelAnimationFrame(resizeFrame);
			resizeFrame = window.requestAnimationFrame(render);
		});
	};

	const setupArtistBio = (bio) => {
		const preview = bio.querySelector('.kogo-artist-single__bio-preview');
		const full = bio.querySelector('.kogo-artist-single__bio-full');
		const toggle = bio.querySelector('.kogo-artist-single__bio-toggle');
		if (!preview || !full || !toggle) {
			return;
		}

		preview.hidden = false;
		full.hidden = true;
		toggle.hidden = false;
		toggle.addEventListener('click', () => {
			const expanded = toggle.getAttribute('aria-expanded') === 'true';
			preview.hidden = !expanded;
			full.hidden = expanded;
			toggle.setAttribute('aria-expanded', String(!expanded));
			toggle.textContent = expanded ? 'Show full text' : 'Show less';
		});
	};

	const setupArtistWorks = (section) => {
		const items = Array.from(section.querySelectorAll('.kogo-artist-works__grid > li'));
		const button = section.querySelector('.kogo-artist-works__more button');
		const status = section.querySelector('[aria-live]');
		const initial = Number(section.dataset.initialItems) || 12;
		const pageSize = Number(section.dataset.pageSize) || 8;
		let visible = Math.min(initial, items.length);

		if (!button || items.length <= initial) {
			return;
		}

		const update = () => {
			items.forEach((item, index) => {
				item.hidden = index >= visible;
			});
			button.hidden = visible >= items.length;
			if (status) {
				status.textContent = `${visible} of ${items.length} works shown`;
			}
		};

		button.addEventListener('click', () => {
			visible = Math.min(visible + pageSize, items.length);
			update();
		});
		update();
	};

	const setupSiteSearch = () => {
		const toggle = document.querySelector('.kogo-header__icon-button--search');
		const panel = document.querySelector('#kogo-site-search');
		const input = panel?.querySelector('input[type="search"]');
		const close = panel?.querySelector('.kogo-search-panel__close');
		const pagePanel = document.querySelector('#kogo-search-page');
		const pageInput = pagePanel?.querySelector('input[type="search"]');

		if (!toggle || !panel || !input) {
			return;
		}

		const searchTerm = new URLSearchParams(window.location.search).get('s');
		if (searchTerm) {
			input.value = searchTerm;
			if (pageInput) {
				pageInput.value = searchTerm;
			}
		}

		if (pageInput) {
			toggle.setAttribute('aria-controls', 'kogo-search-page');
			toggle.removeAttribute('aria-expanded');
			toggle.addEventListener('click', () => pageInput.focus());
			return;
		}

		if (!close) {
			return;
		}

		const setOpen = (open, focus = true) => {
			panel.hidden = !open;
			toggle.setAttribute('aria-expanded', String(open));
			if (focus) {
				(open ? input : toggle).focus();
			}
		};

		toggle.addEventListener('click', () => setOpen(panel.hidden));
		close.addEventListener('click', () => setOpen(false));
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && !panel.hidden) {
				setOpen(false);
			}
		});
	};

	setupSiteSearch();
	document.querySelectorAll('.kogo-hero-slider').forEach(setupHeroSlider);
	document.querySelectorAll('.kogo-posts-slider').forEach(setupPostsSlider);
	document.querySelectorAll('.kogo-gallery-grid-wrap').forEach(setupGallery);
	document.querySelectorAll('[data-artist-bio]').forEach(setupArtistBio);
	document.querySelectorAll('[data-artist-works]').forEach(setupArtistWorks);

	// Switching to mobile: https://developer.mozilla.org/en-US/docs/Web/API/MediaQueryList/onchange
	const isMobile = window.matchMedia(
		'(max-width: ' + getComputedStyle(document.body).getPropertyValue('--custom--media-max-width--sm') + ')'
	);
	const navigationResponsiveContainer = document.querySelector(
		'.site-header .wp-block-navigation__responsive-container'
	);
	isMobile.onchange = (e) => {
		if (e.matches) {
			// <= Mobile
		} else {
			// > Mobile
			// Autoclose header nav container if modal is open and browser window gets resized.
			if (
				document.body.contains(navigationResponsiveContainer) &&
				navigationResponsiveContainer.classList.contains('is-menu-open')
			) {
				document.querySelector('.site-header .wp-block-navigation__responsive-container-close').click();
			}
		}
	};

	// Style password protected post form.
	const passwordButton = document.querySelector('.post-password-form [type="submit"]');
	if (document.body.contains(passwordButton)) {
		const passwordButtonWrapper = document.createElement('div');
		passwordButtonWrapper.classList.add('wp-block-button');
		passwordButton.parentNode.insertBefore(passwordButtonWrapper, passwordButton);
		passwordButtonWrapper.appendChild(passwordButton);

		passwordButton.classList.add('wp-block-button__link');
	}
})();
