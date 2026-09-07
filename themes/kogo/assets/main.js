import Swiper from 'swiper';
import { A11y, Autoplay, Navigation } from 'swiper/modules';
import 'swiper/css';

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

	document.querySelectorAll('.kogo-hero-slider').forEach(setupHeroSlider);

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
