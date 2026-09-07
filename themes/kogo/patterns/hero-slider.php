<?php
/**
 * Title: Hero Slider
 * Slug: kogo/hero-slider
 * Categories: kogo, featured
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: hero, slider, exhibition, featured
 * Description: Editable exhibition hero with autoplay, progress, and navigation controls.
 */

$current_icon_url  = get_template_directory_uri() . '/assets/images/icons/red-dot.svg';
$upcoming_icon_url = get_template_directory_uri() . '/assets/images/icons/loading-arrow.svg';
$featured_icon_url = get_template_directory_uri() . '/assets/images/icons/green-dot.svg';
?>
<!-- wp:group {"align":"full","className":"kogo-hero-slider swiper","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull kogo-hero-slider swiper">
	<!-- wp:group {"className":"kogo-hero-slider__slides swiper-wrapper","layout":{"type":"default"}} -->
	<div class="wp-block-group kogo-hero-slider__slides swiper-wrapper">
		<!-- wp:cover {"url":"https://placehold.co/1800x900/8b8b8b/ffffff/png","dimRatio":20,"overlayColor":"black","isUserOverlayColor":true,"contentPosition":"center center","isDark":true,"className":"kogo-hero-slider__slide swiper-slide"} -->
		<div class="wp-block-cover is-dark has-custom-content-position is-position-center-center kogo-hero-slider__slide swiper-slide"><img class="wp-block-cover__image-background" alt="" src="https://placehold.co/1800x900/8b8b8b/ffffff/png" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-20 has-background-dim"></span><div class="wp-block-cover__inner-container">
			<!-- wp:group {"className":"kogo-hero-slider__slide-inner","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-hero-slider__slide-inner">
				<!-- wp:group {"className":"kogo-hero-slider__tag","layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group kogo-hero-slider__tag">
					<!-- wp:image {"width":"12px","height":"12px","scale":"cover","sizeSlug":"full","linkDestination":"none","className":"kogo-hero-slider__tag-icon"} -->
					<figure class="wp-block-image size-full is-resized kogo-hero-slider__tag-icon"><img src="<?php echo esc_url( $current_icon_url ); ?>" alt="" style="object-fit:cover;width:12px;height:12px"/></figure>
					<!-- /wp:image -->
					<!-- wp:paragraph -->
					<p>Current fair</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"kogo-hero-slider__content","layout":{"type":"default"}} -->
				<div class="wp-block-group kogo-hero-slider__content">
					<!-- wp:paragraph {"className":"kogo-hero-slider__date"} -->
					<p class="kogo-hero-slider__date">02.07.2026–05.07.2026</p>
					<!-- /wp:paragraph -->
					<!-- wp:heading {"level":2,"className":"kogo-hero-slider__title"} -->
					<h2 class="wp-block-heading kogo-hero-slider__title"><a href="#">Riga Contemporary</a></h2>
					<!-- /wp:heading -->
					<!-- wp:paragraph {"className":"kogo-hero-slider__artists"} -->
					<p class="kogo-hero-slider__artists">Elīna Vītola, Eike Eplik</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div></div>
		<!-- /wp:cover -->

		<!-- wp:cover {"url":"https://placehold.co/1800x900/202020/ffffff/png","dimRatio":20,"overlayColor":"black","isUserOverlayColor":true,"contentPosition":"center center","isDark":true,"className":"kogo-hero-slider__slide swiper-slide"} -->
		<div class="wp-block-cover is-dark has-custom-content-position is-position-center-center kogo-hero-slider__slide swiper-slide"><img class="wp-block-cover__image-background" alt="" src="https://placehold.co/1800x900/202020/ffffff/png" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-20 has-background-dim"></span><div class="wp-block-cover__inner-container">
			<!-- wp:group {"className":"kogo-hero-slider__slide-inner","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-hero-slider__slide-inner">
				<!-- wp:group {"className":"kogo-hero-slider__tag","layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group kogo-hero-slider__tag">
					<!-- wp:image {"width":"12px","height":"12px","scale":"cover","sizeSlug":"full","linkDestination":"none","className":"kogo-hero-slider__tag-icon"} -->
					<figure class="wp-block-image size-full is-resized kogo-hero-slider__tag-icon"><img src="<?php echo esc_url( $upcoming_icon_url ); ?>" alt="" style="object-fit:cover;width:12px;height:12px"/></figure>
					<!-- /wp:image -->
					<!-- wp:paragraph -->
					<p>Upcoming exhibition</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"kogo-hero-slider__content","layout":{"type":"default"}} -->
				<div class="wp-block-group kogo-hero-slider__content">
					<!-- wp:paragraph {"className":"kogo-hero-slider__date"} -->
					<p class="kogo-hero-slider__date">12.09.2026–23.10.2026</p>
					<!-- /wp:paragraph -->
					<!-- wp:heading {"level":2,"className":"kogo-hero-slider__title"} -->
					<h2 class="wp-block-heading kogo-hero-slider__title"><a href="#">Floral Atlas</a></h2>
					<!-- /wp:heading -->
					<!-- wp:paragraph {"className":"kogo-hero-slider__artists"} -->
					<p class="kogo-hero-slider__artists">Timo Toots, Mari-Liis Rebane</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div></div>
		<!-- /wp:cover -->

		<!-- wp:cover {"url":"https://placehold.co/1800x900/b5b5b5/ffffff/png","dimRatio":20,"overlayColor":"black","isUserOverlayColor":true,"contentPosition":"center center","isDark":true,"className":"kogo-hero-slider__slide swiper-slide"} -->
		<div class="wp-block-cover is-dark has-custom-content-position is-position-center-center kogo-hero-slider__slide swiper-slide"><img class="wp-block-cover__image-background" alt="" src="https://placehold.co/1800x900/b5b5b5/ffffff/png" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-20 has-background-dim"></span><div class="wp-block-cover__inner-container">
			<!-- wp:group {"className":"kogo-hero-slider__slide-inner","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-hero-slider__slide-inner">
				<!-- wp:group {"className":"kogo-hero-slider__tag","layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group kogo-hero-slider__tag">
					<!-- wp:image {"width":"12px","height":"12px","scale":"cover","sizeSlug":"full","linkDestination":"none","className":"kogo-hero-slider__tag-icon"} -->
					<figure class="wp-block-image size-full is-resized kogo-hero-slider__tag-icon"><img src="<?php echo esc_url( $featured_icon_url ); ?>" alt="" style="object-fit:cover;width:12px;height:12px"/></figure>
					<!-- /wp:image -->
					<!-- wp:paragraph -->
					<p>Featured artist</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"className":"kogo-hero-slider__content","layout":{"type":"default"}} -->
				<div class="wp-block-group kogo-hero-slider__content">
					<!-- wp:paragraph {"className":"kogo-hero-slider__date"} -->
					<p class="kogo-hero-slider__date">01.11.2026–15.01.2027</p>
					<!-- /wp:paragraph -->
					<!-- wp:heading {"level":2,"className":"kogo-hero-slider__title"} -->
					<h2 class="wp-block-heading kogo-hero-slider__title"><a href="#">Material Memory</a></h2>
					<!-- /wp:heading -->
					<!-- wp:paragraph {"className":"kogo-hero-slider__artists"} -->
					<p class="kogo-hero-slider__artists">Group exhibition</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div></div>
		<!-- /wp:cover -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
