<?php
/**
 * Title: Image Slider
 * Slug: kogo/image-slider
 * Categories: kogo, gallery
 * Viewport Width: 900
 * Inserter: yes
 * Keywords: gallery, images, slider, lightbox
 * Description: Editable WordPress gallery with one image per slide, centered arrows, a counter and native image lightboxes.
 */

$uploads_url = wp_get_upload_dir()['baseurl'] . '/2026/09/';
$images = array(
	array( 'e6ba914bed1f607687e2f1bbef41125f28ff79751.jpg', 'Visitors talking on a sofa in the Kogo showroom' ),
	array( '1ef97cc31c458a60adad23052187a612a1473672-scaled.png', 'Colourful artworks around a yellow wall at Kogo Gallery' ),
	array( '7f93f001ec76baec2e33e49464389c2aa89951af.png', 'Guests in conversation at a Kogo Gallery opening' ),
);
?>
<!-- wp:group {"className":"kogo-image-slider","layout":{"type":"default"}} -->
<div class="wp-block-group kogo-image-slider">
	<!-- wp:group {"className":"kogo-image-slider__viewport swiper","layout":{"type":"default"}} -->
	<div class="wp-block-group kogo-image-slider__viewport swiper">
		<!-- wp:gallery {"columns":1,"imageCrop":false,"linkTo":"none","sizeSlug":"large","className":"kogo-image-slider__slides swiper-wrapper"} -->
		<figure class="wp-block-gallery has-nested-images columns-1 kogo-image-slider__slides swiper-wrapper">
			<?php foreach ( $images as $image ) :
				$image_url = $uploads_url . $image[0];
				$image_id = attachment_url_to_postid( $image_url );
				$image_url = wp_get_attachment_image_url( $image_id, 'large' ) ?: $image_url;
				?>
				<!-- wp:image {"id":<?php echo (int) $image_id; ?>,"sizeSlug":"large","linkDestination":"none","lightbox":{"enabled":true}} -->
				<figure class="wp-block-image size-large"><img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image[1] ); ?>" class="wp-image-<?php echo (int) $image_id; ?>"/></figure>
				<!-- /wp:image -->
			<?php endforeach; ?>
		</figure>
		<!-- /wp:gallery -->
	</div>
	<!-- /wp:group -->
	<!-- wp:paragraph {"className":"kogo-image-slider__caption"} -->
	<p class="kogo-image-slider__caption">Kogo showroom</p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
