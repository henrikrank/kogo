<?php

define( 'ABSPATH', __DIR__ . '/' );

function add_shortcode() {}
function __( $text ) {
	return $text;
}
function get_post_meta( $post_id, $key ) {
	$fixtures = array(
		95  => array(
			'_kogo_itgallery_payload'             => array(
				'photo_sizes' => array(
					'url'    => 'https://images.example/artist.jpg',
					'small'  => 'https://images.example/artist-100.jpg',
					'medium' => 'https://images.example/artist-500.jpg',
					'large'  => 'https://images.example/artist-1000.jpg',
				),
			),
			'_kogo_itgallery_exposition_post_ids' => array( 453, 454 ),
			'_kogo_itgallery_work_post_ids'       => array( 74, 75, 76 ),
		),
	);

	return $fixtures[ $post_id ][ $key ] ?? '';
}
function get_post_field() {
	return '';
}
function get_the_post_thumbnail_url() {
	return '';
}
function kogo_itgallery_get_image_url() {
	return '';
}
function kogo_get_exhibition_gallery_images( $post_id ) {
	return array(
		array(
			'url'    => 'https://images.example/shared.jpg',
			'src'    => 'https://images.example/shared-large.jpg',
			'small'  => '',
			'medium' => '',
			'large'  => 'https://images.example/shared-large.jpg',
		),
		array(
			'url'    => 'https://images.example/' . $post_id . '.jpg',
			'src'    => 'https://images.example/' . $post_id . '-large.jpg',
			'small'  => '',
			'medium' => '',
			'large'  => 'https://images.example/' . $post_id . '-large.jpg',
		),
	);
}
function get_post_status( $post_id ) {
	return 75 === $post_id ? 'draft' : 'publish';
}
function absint( $value ) {
	return abs( (int) $value );
}
function esc_url_raw( $value ) {
	return $value;
}
function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

require dirname( __DIR__ ) . '/themes/kogo/inc/artist-single.php';

$bio = kogo_parse_artist_bio( '<p>Lead paragraph.</p><p> </p><p>Continuation.</p>' );
if ( '<p>Lead paragraph.</p>' !== $bio['lead'] || '<p>Continuation.</p>' !== $bio['body'] ) {
	fwrite( STDERR, "Artist biography was not split into lead and continuation.\n" );
	exit( 1 );
}

$long_bio = kogo_parse_artist_bio(
	'<p>First sentence establishes the artist and describes a focused practice through materials, observation, memory, careful research, and patient experimentation. Second sentence adds concise context about recurring forms, collaborative methods, changing installations, public encounters, and relationships with particular places. Third sentence contains substantially more detail that belongs after the gallery instead of making the emphasized introduction excessively long for readers and overwhelming the opening section.</p><p>Existing continuation.</p>'
);
if ( false === strpos( $long_bio['lead'], 'Second sentence' ) || false !== strpos( $long_bio['lead'], 'Third sentence' ) || false === strpos( $long_bio['body'], 'Third sentence' ) ) {
	fwrite( STDERR, "Long artist biography was not split at a sentence boundary.\n" );
	exit( 1 );
}

if ( 5 !== kogo_artist_word_count( 'Sabīne Vernere makes mixed-media work.' ) ) {
	fwrite( STDERR, "Artist biography word count did not handle non-ASCII text.\n" );
	exit( 1 );
}

$portrait = kogo_get_artist_portrait( 95 );
if ( 'https://images.example/artist-1000.jpg' !== $portrait['src'] || false === strpos( $portrait['srcset'], 'artist-500.jpg 500w' ) ) {
	fwrite( STDERR, "Artist portrait did not use responsive imported image metadata.\n" );
	exit( 1 );
}

$gallery = kogo_get_artist_gallery_images( 95 );
if ( 3 !== count( $gallery ) ) {
	fwrite( STDERR, "Artist exhibition gallery images were not combined and deduplicated.\n" );
	exit( 1 );
}

if ( array( 74, 76 ) !== kogo_get_artist_work_ids( 95 ) ) {
	fwrite( STDERR, "Artist works did not exclude unpublished records.\n" );
	exit( 1 );
}

echo "Kogo single artist test passed.\n";
