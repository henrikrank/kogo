<?php
/**
 * Title: Related Exhibitions
 * Slug: kogo/linked-exhibitions
 * Categories: kogo, posts
 * Viewport Width: 1440
 * Inserter: yes
 * Description: Editorially linked exhibitions using the homepage artist-grid layout.
 */

?>
<!-- wp:group {"tagName":"section","align":"full","className":"kogo-artists kogo-linked-exhibitions-section","layout":{"type":"default"}} -->
<section class="wp-block-group alignfull kogo-artists kogo-linked-exhibitions-section">
	<!-- wp:columns {"className":"kogo-artists__columns"} -->
	<div class="wp-block-columns kogo-artists__columns">
		<!-- wp:column {"width":"33.333%"} -->
		<div class="wp-block-column" style="flex-basis:33.333%">
			<!-- wp:group {"className":"kogo-artists__intro","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-artists__intro">
				<!-- wp:heading {"className":"kogo-artists__heading"} -->
				<h2 class="wp-block-heading kogo-artists__heading"><?php esc_html_e( 'Related exhibitions', 'kogo' ); ?></h2>
				<!-- /wp:heading -->

				<!-- wp:buttons -->
				<div class="wp-block-buttons">
					<!-- wp:button {"className":"is-style-outline kogo-artists__more"} -->
					<div class="wp-block-button is-style-outline kogo-artists__more"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( get_post_type_archive_link( 'kogo_exposition' ) ); ?>"><?php esc_html_e( 'More', 'kogo' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"66.667%","className":"kogo-artists__listing"} -->
		<div class="wp-block-column kogo-artists__listing" style="flex-basis:66.667%">
			<!-- wp:query {"queryId":90405,"query":{"perPage":100,"pages":0,"offset":0,"postType":"kogo_exposition","order":"asc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"kogo-artists__query","layout":{"type":"default"}} -->
			<div class="wp-block-query kogo-artists__query">
				<!-- wp:post-template {"className":"kogo-artists__grid","layout":{"type":"grid","columnCount":2}} -->
					<!-- wp:group {"className":"kogo-artists__card kogo-linked-exhibitions__card","layout":{"type":"default"}} -->
					<div class="wp-block-group kogo-artists__card kogo-linked-exhibitions__card">
						<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2","sizeSlug":"large","className":"kogo-artists__image kogo-exhibition-card__image"} /-->

						<!-- wp:shortcode -->
						[kogo_exhibition_dates]
						<!-- /wp:shortcode -->

						<!-- wp:post-title {"isLink":true,"level":3,"className":"kogo-artists__title"} /-->

						<!-- wp:shortcode -->
						[kogo_linked_exhibition_credits]
						<!-- /wp:shortcode -->
					</div>
					<!-- /wp:group -->
				<!-- /wp:post-template -->
			</div>
			<!-- /wp:query -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
