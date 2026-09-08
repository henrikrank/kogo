<?php
/**
 * Title: Gallery Grid
 * Slug: kogo/gallery-grid
 * Categories: kogo, gallery
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: gallery, images, lightbox, grid
 * Description: Responsive six-column image gallery with the WordPress lightbox enabled.
 */

$uploads_url = wp_get_upload_dir()['baseurl'] . '/2026/09';
$gallery_images = array(
	array( 'a3e1d0bff949534144b964718557458cf05b8f6a.png', __( 'Exhibition installation view', 'kogo' ) ),
	array( '71ccaca5c99379b69e4eeb4634377f635eb8bf9a.png', __( 'Art fair installation view', 'kogo' ) ),
	array( 'c2a82db7853e0124c8858ddcacb51ce22d494e29.png', __( 'Light installation view', 'kogo' ) ),
	array( 'a3e1d0bff949534144b964718557458cf05b8f6a.png', __( 'Exhibition installation detail', 'kogo' ) ),
	array( '71ccaca5c99379b69e4eeb4634377f635eb8bf9a.png', __( 'Art fair installation detail', 'kogo' ) ),
	array( 'c2a82db7853e0124c8858ddcacb51ce22d494e29.png', __( 'Light installation detail', 'kogo' ) ),
	array( '71ccaca5c99379b69e4eeb4634377f635eb8bf9a.png', __( 'Artwork display', 'kogo' ) ),
	array( 'a3e1d0bff949534144b964718557458cf05b8f6a.png', __( 'Textile exhibition view', 'kogo' ) ),
	array( 'c2a82db7853e0124c8858ddcacb51ce22d494e29.png', __( 'Immersive artwork view', 'kogo' ) ),
	array( '71ccaca5c99379b69e4eeb4634377f635eb8bf9a.png', __( 'Gallery wall detail', 'kogo' ) ),
	array( 'c2a82db7853e0124c8858ddcacb51ce22d494e29.png', __( 'Illuminated artwork detail', 'kogo' ) ),
	array( 'a3e1d0bff949534144b964718557458cf05b8f6a.png', __( 'Gallery room view', 'kogo' ) ),
);
?>
<!-- wp:group {"align":"full","className":"kogo-gallery-grid-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull kogo-gallery-grid-wrap">
	<!-- wp:gallery {"columns":6,"linkTo":"none","sizeSlug":"large","className":"kogo-gallery-grid"} -->
	<figure class="wp-block-gallery has-nested-images columns-6 is-cropped kogo-gallery-grid">
		<?php foreach ( $gallery_images as $gallery_image ) : ?>
			<!-- wp:image {"sizeSlug":"large","linkDestination":"none","lightbox":{"enabled":true}} -->
			<figure class="wp-block-image size-large"><img src="<?php echo esc_url( $uploads_url . '/' . $gallery_image[0] ); ?>" alt="<?php echo esc_attr( $gallery_image[1] ); ?>"/></figure>
			<!-- /wp:image -->
		<?php endforeach; ?>
	</figure>
	<!-- /wp:gallery -->
</div>
<!-- /wp:group -->
