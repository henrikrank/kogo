<?php
/**
 * Title: Posts Cover Grid
 * Slug: kogo/posts-cover-grid
 * Categories: kogo, posts
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: posts, query, exhibitions, cover, grid
 * Description: Configurable two-column post grid with featured-image cover cards.
 */
?>
<!-- wp:query {"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","categoryIds":[],"tagIds":[],"order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"full","className":"kogo-posts-cover-grid","layout":{"type":"default"}} -->
<div class="wp-block-query alignfull kogo-posts-cover-grid">
	<!-- wp:heading {"level":2,"className":"kogo-posts-cover-grid__heading"} -->
	<h2 class="wp-block-heading kogo-posts-cover-grid__heading">Current exhibitions</h2>
	<!-- /wp:heading -->

	<!-- wp:post-template {"className":"kogo-posts-cover-grid__items","layout":{"type":"grid","columnCount":2}} -->
		<!-- wp:cover {"useFeaturedImage":true,"dimRatio":0,"contentPosition":"bottom left","isDark":true,"className":"kogo-posts-cover-grid__card"} -->
		<div class="wp-block-cover is-dark has-custom-content-position is-position-bottom-left kogo-posts-cover-grid__card"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container">
			<!-- wp:group {"textColor":"white","className":"kogo-posts-cover-grid__content","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-posts-cover-grid__content has-white-color has-text-color">
				<!-- wp:post-date {"format":"d.m.Y","fontSize":"small","className":"kogo-posts-cover-grid__date"} /-->

				<!-- wp:post-title {"isLink":true,"level":3,"className":"kogo-posts-cover-grid__title"} /-->

				<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false,"excerptLength":32,"className":"kogo-posts-cover-grid__excerpt"} /-->
			</div>
			<!-- /wp:group -->
		</div></div>
		<!-- /wp:cover -->
	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size">No posts found.</p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
