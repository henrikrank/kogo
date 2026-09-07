<?php
/**
 * Title: Posts Slider
 * Slug: kogo/posts-slider
 * Categories: kogo, posts
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: posts, news, query, slider, carousel
 * Description: Configurable latest-posts query displayed as a one-sided full-bleed carousel.
 */

$posts_archive_url = get_post_type_archive_link( 'post' ) ?: home_url( '/' );
?>
<!-- wp:separator {"className":"is-style-default"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-default"/>
<!-- /wp:separator -->

<!-- wp:query {"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"full","className":"kogo-posts-slider swiper","layout":{"type":"default"}} -->
<div class="wp-block-query alignfull kogo-posts-slider swiper">
	<!-- wp:group {"className":"kogo-posts-slider__header","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group kogo-posts-slider__header">
		<!-- wp:heading {"level":2,"className":"kogo-posts-slider__heading"} -->
		<h2 class="wp-block-heading kogo-posts-slider__heading">News</h2>
		<!-- /wp:heading -->

		<!-- wp:group {"className":"kogo-posts-slider__actions","layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group kogo-posts-slider__actions">
			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-outline kogo-posts-slider__more"} -->
				<div class="wp-block-button is-style-outline kogo-posts-slider__more"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $posts_archive_url ); ?>">More</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:post-template {"className":"kogo-posts-slider__items swiper-wrapper","layout":{"type":"default"}} -->
		<!-- wp:group {"className":"kogo-posts-slider__card","layout":{"type":"default"}} -->
		<div class="wp-block-group kogo-posts-slider__card">
			<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2","sizeSlug":"large","className":"kogo-posts-slider__image"} /-->

			<!-- wp:post-date {"format":"d.m.Y","fontSize":"small","className":"kogo-posts-slider__date"} /-->

			<!-- wp:post-title {"isLink":true,"level":3,"className":"kogo-posts-slider__title"} /-->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">No posts found.</p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->

<!-- wp:separator {"className":"is-style-default"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-default"/>
<!-- /wp:separator -->
