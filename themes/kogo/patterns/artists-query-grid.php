<?php
/**
 * Title: Artists Query Grid
 * Slug: kogo/artists-query-grid
 * Categories: kogo, posts
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: artists, query, grid, profiles
 * Description: Introductory artist listing with a configurable two-column Query Loop.
 */

?>
<!-- wp:group {"align":"full","className":"kogo-artists","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull kogo-artists">
	<!-- wp:spacer {"height":"64px"} -->
	<div style="height:64px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:columns {"className":"kogo-artists__columns"} -->
	<div class="wp-block-columns kogo-artists__columns">
		<!-- wp:column {"width":"33.333%"} -->
		<div class="wp-block-column" style="flex-basis:33.333%">
			<!-- wp:group {"className":"kogo-artists__intro","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-artists__intro">
				<!-- wp:heading {"level":2,"className":"kogo-artists__heading"} -->
				<h2 class="wp-block-heading kogo-artists__heading">Gallery artists</h2>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"fontSize":"small","className":"kogo-artists__description"} -->
				<p class="kogo-artists__description has-small-font-size">Meet the artists represented by Kogo. A lot of other artists have had a show in our gallery, check them out too!</p>
				<!-- /wp:paragraph -->

				<!-- wp:buttons -->
				<div class="wp-block-buttons">
					<!-- wp:button {"className":"is-style-outline kogo-artists__more"} -->
					<div class="wp-block-button is-style-outline kogo-artists__more"><a class="wp-block-button__link wp-element-button" href="/artists#exhibited-artists">Exhibited artists</a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"66.667%","className":"kogo-artists__listing"} -->
		<div class="wp-block-column kogo-artists__listing" style="flex-basis:66.667%">
			<!-- wp:query {"queryId":90403,"query":{"perPage":8,"pages":0,"offset":0,"postType":"kogo_artist","order":"asc","orderBy":"title","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"kogo-artists__query kogo-artists__query--featured","layout":{"type":"default"}} -->
			<div class="wp-block-query kogo-artists__query kogo-artists__query--featured">
				<!-- wp:post-template {"className":"kogo-artists__grid","layout":{"type":"grid","columnCount":2}} -->
					<!-- wp:group {"className":"kogo-artists__card","layout":{"type":"default"}} -->
					<div class="wp-block-group kogo-artists__card">
						<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2","sizeSlug":"large","className":"kogo-artists__image"} /-->

						<!-- wp:post-title {"isLink":true,"level":3,"className":"kogo-artists__title"} /-->

						<!-- wp:shortcode {"className":"kogo-artists__disciplines"} -->
						<div class="wp-block-shortcode kogo-artists__disciplines">[kogo_artist_disciplines]</div>
						<!-- /wp:shortcode -->
					</div>
					<!-- /wp:group -->
				<!-- /wp:post-template -->

				<!-- wp:query-no-results -->
					<!-- wp:paragraph {"fontSize":"small"} -->
					<p class="has-small-font-size">No artists found.</p>
					<!-- /wp:paragraph -->
				<!-- /wp:query-no-results -->
			</div>
			<!-- /wp:query -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:spacer {"height":"64px"} -->
	<div style="height:64px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
</div>
<!-- /wp:group -->

<!-- wp:separator {"className":"is-style-default"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-default"/>
<!-- /wp:separator -->
