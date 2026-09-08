<?php

define( 'ABSPATH', __DIR__ . '/' );

function add_filter() {}
function add_shortcode() {}
function get_posts() {
	return array( 11, 12, 13 );
}
function get_post_meta( $post_id, $key ) {
	$meta = array(
		11  => array( '_kogo_itgallery_exposition_post_ids' => array( 101, 102 ) ),
		12  => array( '_kogo_itgallery_exposition_post_ids' => array( 102, 103 ) ),
		13  => array( '_kogo_itgallery_exposition_post_ids' => array() ),
		101 => array( '_kogo_itgallery_opening_date' => '2025-02-01' ),
		102 => array( '_kogo_itgallery_opening_date' => '2025-09-01' ),
		103 => array( '_kogo_itgallery_opening_date' => '2024-03-01' ),
	);

	return $meta[ $post_id ][ $key ] ?? '';
}
function get_the_title( $post_id ) {
	return array( 11 => 'Artist A', 12 => 'Artist B' )[ $post_id ] ?? '';
}
function get_permalink( $post_id ) {
	return 'https://example.test/artists/' . $post_id;
}
function get_the_ID() {
	return 11;
}
function esc_html__( $value ) {
	return $value;
}
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $value ) {
	return $value;
}

require dirname( __DIR__ ) . '/themes/kogo/inc/artist-grid.php';

$query = kogo_filter_artists_grid_query(
	array( 'post_type' => 'post' ),
	(object) array(
		'context'      => array( 'queryId' => KOGO_FEATURED_ARTISTS_QUERY_ID ),
		'parsed_block' => array( 'attrs' => array( 'className' => 'kogo-artists__grid' ) ),
	)
);
if ( 'kogo_artist' !== $query['post_type'] || array( 'featured' ) !== $query['tax_query'][0]['terms'] ) {
	fwrite( STDERR, "The Gallery artists grid did not select Featured artists.\n" );
	exit( 1 );
}

$years = kogo_get_exhibited_artists_by_year();
if ( array( 2025, 2024 ) !== array_keys( $years ) || 2 !== count( $years[2025] ) || 1 !== count( $years[2024] ) ) {
	fwrite( STDERR, "Exhibited artists were not grouped and deduplicated by opening year.\n" );
	exit( 1 );
}

$html = kogo_exhibited_artists_shortcode();
if ( 1 !== substr_count( $html, '>Artist A</a>' ) || 2 !== substr_count( $html, '>Artist B</a>' ) || false === strpos( $html, '>2024</h3>' ) ) {
	fwrite( STDERR, "The exhibited-artist year index markup is incorrect.\n" );
	exit( 1 );
}

$rendered = kogo_render_artist_shortcode_block(
	'<p>[kogo_exhibited_artists]</p>',
	array( 'innerHTML' => '[kogo_exhibited_artists]' ),
	(object) array( 'context' => array() )
);
if ( false === strpos( $rendered, 'kogo-exhibited-artists__years' ) || false !== strpos( $rendered, '[kogo_exhibited_artists]' ) ) {
	fwrite( STDERR, "The exhibited-artist pattern shortcode was not rendered.\n" );
	exit( 1 );
}

echo "Kogo artist archive test passed.\n";
