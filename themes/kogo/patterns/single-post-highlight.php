<?php
/**
 * Title: Single Post Highlight
 * Slug: kogo/single-post-highlight
 * Categories: kogo, posts
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: featured post, highlight, article, query
 * Description: A configurable single-post feature with editorial copy and a large image.
 */

$highlight_category    = get_category_by_slug( 'artist-highlight' );
$highlight_category_id = $highlight_category ? (int) $highlight_category->term_id : 0;
?>
<!-- wp:group {"align":"full","className":"kogo-post-highlight","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull kogo-post-highlight">
	<!-- wp:spacer {"height":"64px"} -->
	<div style="height:64px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:query {"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","categoryIds":[<?php echo $highlight_category_id; ?>],"order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"kogo-post-highlight__query","layout":{"type":"default"}} -->
	<div class="wp-block-query kogo-post-highlight__query">
		<!-- wp:post-template {"className":"kogo-post-highlight__items","layout":{"type":"default"}} -->
			<!-- wp:columns {"className":"kogo-post-highlight__columns"} -->
			<div class="wp-block-columns kogo-post-highlight__columns">
				<!-- wp:column {"width":"50%"} -->
				<div class="wp-block-column" style="flex-basis:50%">
					<!-- wp:group {"className":"kogo-post-highlight__content","layout":{"type":"default"}} -->
					<div class="wp-block-group kogo-post-highlight__content">
						<!-- wp:paragraph {"fontSize":"small","className":"kogo-post-highlight__label"} -->
						<p class="kogo-post-highlight__label has-small-font-size">Artist Highlight</p>
						<!-- /wp:paragraph -->

						<!-- wp:post-title {"isLink":true,"level":2,"className":"kogo-post-highlight__title"} /-->

						<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false,"className":"kogo-post-highlight__excerpt"} /-->

						<!-- wp:read-more {"content":"Read article","className":"kogo-post-highlight__more"} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:column -->

				<!-- wp:column {"width":"50%","className":"kogo-post-highlight__media"} -->
				<div class="wp-block-column kogo-post-highlight__media" style="flex-basis:50%">
					<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1","sizeSlug":"large","className":"kogo-post-highlight__image"} /-->
				</div>
				<!-- /wp:column -->
			</div>
			<!-- /wp:columns -->
		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size">No highlighted post found.</p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:query -->

	<!-- wp:spacer {"height":"64px"} -->
	<div style="height:64px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
</div>
<!-- /wp:group -->

<!-- wp:separator {"className":"is-style-default"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-default"/>
<!-- /wp:separator -->
