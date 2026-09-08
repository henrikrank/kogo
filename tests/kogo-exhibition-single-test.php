<?php

define( 'ABSPATH', __DIR__ . '/' );

function add_shortcode() {}
function __( $text ) {
	return $text;
}
function wp_date( $format ) {
	return 'Y-m-d' === $format ? '2026-09-08' : '';
}
function get_post_meta( $post_id, $key ) {
	$meta = array(
		101 => array(
			'_kogo_itgallery_opening_date'  => '2026-09-01',
			'_kogo_itgallery_closing_date'  => '2026-09-30',
			'_kogo_itgallery_payload'       => array(
				'main_image_sizes' => array(
					'url'       => 'https://images.example/cover.jpg',
					'large_url' => 'https://images.example/cover-large.jpg',
				),
			),
			'_kogo_itgallery_images'        => array(
				array(
					'url'       => 'https://images.example/cover.jpg',
					'large_url' => 'https://images.example/cover-large.jpg',
				),
				array(
					'url'       => 'https://images.example/view.jpg',
					'large_url' => 'https://images.example/view-large.jpg',
				),
			),
			'_kogo_itgallery_work_post_ids' => array( 201 ),
		),
		102 => array(
			'_kogo_itgallery_opening_date' => '2025-01-01',
			'_kogo_itgallery_closing_date' => '2025-02-01',
		),
		103 => array(
			'_kogo_itgallery_opening_date' => '2027-01-01',
			'_kogo_itgallery_closing_date' => '2027-02-01',
		),
		201 => array(
			'_kogo_itgallery_images' => array(
				array(
					'url'       => 'https://images.example/work.jpg',
					'large_url' => 'https://images.example/work-large.jpg',
				),
			),
		),
	);

	return $meta[ $post_id ][ $key ] ?? '';
}
function get_the_post_thumbnail_url() {
	return 'https://images.example/featured.jpg';
}
function esc_url_raw( $url ) {
	return $url;
}
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}
function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

require dirname( __DIR__ ) . '/themes/kogo/inc/exhibition-single.php';

if ( 'current' !== kogo_get_exhibition_status( 101 )[0] || 'past' !== kogo_get_exhibition_status( 102 )[0] || 'upcoming' !== kogo_get_exhibition_status( 103 )[0] ) {
	fwrite( STDERR, "Exhibition status does not follow its synced dates.\n" );
	exit( 1 );
}

if ( 'https://images.example/cover.jpg' !== kogo_get_exhibition_cover_url( 101 ) ) {
	fwrite( STDERR, "The remote ITGallery cover was not preferred.\n" );
	exit( 1 );
}

$images = kogo_get_exhibition_gallery_images( 101 );
if ( 2 !== count( $images ) || 'https://images.example/view.jpg' !== $images[1]['url'] ) {
	fwrite( STDERR, "Exhibition images were not collected and deduplicated.\n" );
	exit( 1 );
}

$sections = kogo_parse_exhibition_content( "Short idea.\n\nLong text.\n\nTEAM\n\nArtists: One\n\nFUNDING\n\nSupported by Two." );
if ( 'Short idea.' !== $sections['idea'] || 'Long text.' !== $sections['text'] || 'Artists: One' !== $sections['team'] || 'Supported by Two.' !== $sections['funding'] ) {
	fwrite( STDERR, "Exhibition content was not split into idea, text, team, and funding.\n" );
	exit( 1 );
}

$dimensions = kogo_format_work_dimensions(
	array(
		array(
			'width'  => '28.50',
			'height' => '38.00',
			'depth'  => '0.00',
			'unit'   => array( 'name' => 'cm' ),
		),
	)
);
if ( '28.5 × 38 cm' !== $dimensions ) {
	fwrite( STDERR, "Work dimensions were not formatted for the slider.\n" );
	exit( 1 );
}

echo "Kogo single exhibition test passed.\n";
