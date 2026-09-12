<?php
/**
 * Title: Split Page Header
 * Slug: kogo/page-split-header
 * Categories: kogo, featured
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: page, header, introduction, visit
 * Description: Page title and editable introduction beside a lightbox-enabled image, using the single-page header layout.
 */

$image_url = wp_get_upload_dir()['baseurl'] . '/2026/09/8d8b2bbe34e89de50ee5216a77a4a9b2d49411ae.jpg';
$image_id = attachment_url_to_postid( $image_url );
$image_url = wp_get_attachment_image_url( $image_id, 'large' ) ?: $image_url;
?>
<!-- wp:group {"align":"full","className":"kogo-single-post__hero kogo-page-split-header","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull kogo-single-post__hero kogo-page-split-header">
	<!-- wp:columns {"className":"kogo-single-post__hero-columns"} -->
	<div class="wp-block-columns kogo-single-post__hero-columns">
		<!-- wp:column {"width":"50%","className":"kogo-single-post__intro"} -->
		<div class="wp-block-column kogo-single-post__intro" style="flex-basis:50%">
			<!-- wp:post-title {"level":1,"className":"kogo-single-post__title"} /-->
			<!-- wp:group {"className":"kogo-page-split-header__copy","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-page-split-header__copy">
				<!-- wp:paragraph -->
				<p>Add your opening hours, contact details or page introduction here.</p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"width":"50%","className":"kogo-single-post__media"} -->
		<div class="wp-block-column kogo-single-post__media" style="flex-basis:50%">
			<!-- wp:image {"id":<?php echo (int) $image_id; ?>,"sizeSlug":"large","linkDestination":"none","lightbox":{"enabled":true},"className":"kogo-single-post__image"} -->
			<figure class="wp-block-image size-large kogo-single-post__image"><img src="<?php echo esc_url( $image_url ); ?>" alt="Kogo Gallery entrance in the Aparaaditehas courtyard" class="wp-image-<?php echo (int) $image_id; ?>"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
