<?php
/**
 * Title: Posts Cover Grid
 * Slug: kogo/posts-cover-grid
 * Categories: kogo, posts
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: posts, query, exhibitions, cover, grid
 * Description: Current exhibitions in a two-column featured-image cover grid.
 */
?>
<!-- wp:query {"queryId":90401,"query":{"perPage":4,"pages":0,"offset":0,"postType":"kogo_exposition","order":"asc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"full","className":"kogo-posts-cover-grid","layout":{"type":"default"}} -->
<div class="wp-block-query alignfull kogo-posts-cover-grid">
	<!-- wp:heading {"level":2,"className":"kogo-posts-cover-grid__heading"} -->
	<h2 class="wp-block-heading kogo-posts-cover-grid__heading">Current exhibitions</h2>
	<!-- /wp:heading -->

	<!-- wp:post-template {"className":"kogo-posts-cover-grid__items","layout":{"type":"grid","columnCount":2}} -->
		<!-- wp:group {"className":"kogo-posts-cover-grid__card","layout":{"type":"default"}} -->
		<div class="wp-block-group kogo-posts-cover-grid__card">
			<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2","sizeSlug":"large","className":"kogo-exhibition-card__image kogo-posts-cover-grid__image"} /-->

			<!-- wp:group {"textColor":"white","className":"kogo-posts-cover-grid__content","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-posts-cover-grid__content has-white-color has-text-color">
				<!-- wp:shortcode -->
				[kogo_exhibition_dates]
				<!-- /wp:shortcode -->

				<!-- wp:post-title {"isLink":true,"level":3,"className":"kogo-posts-cover-grid__title"} /-->

				<!-- wp:shortcode -->
				[kogo_exhibition_artists]
				<!-- /wp:shortcode -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">No current exhibitions.</p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
