<?php

define( 'ABSPATH', __DIR__ . '/' );

function add_filter() {}
function add_shortcode() {}
function wp_date( $format, $timestamp = null ) {
	return 'Y-m-d' === $format ? '2026-09-08' : gmdate( $format, $timestamp );
}
function get_the_ID() {
	return 101;
}
function get_post_meta( $post_id, $key ) {
	$fixtures = array(
		101 => array(
			'_kogo_itgallery_opening_date' => '2026-02-12',
			'_kogo_itgallery_closing_date' => '2026-04-11',
			'_kogo_itgallery_payload'      => array( 'artists' => array( array( 'display_name' => 'Sabine Vernere' ) ) ),
		),
		102 => array(
			'_kogo_itgallery_opening_date' => '2025-08-15',
			'_kogo_itgallery_closing_date' => '2025-09-27',
			'_kogo_itgallery_payload'      => array( 'artists' => array( array( 'display_name' => 'Paweł Matyszewski' ) ) ),
		),
	);

	return $fixtures[ $post_id ][ $key ] ?? '';
}
function get_the_title( $post_id ) {
	return 'Artist ' . $post_id;
}
function sanitize_text_field( $value ) {
	return trim( $value );
}
function esc_html( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

require dirname( __DIR__ ) . '/themes/kogo/inc/exhibition-archive.php';

$current = kogo_filter_exhibition_archive_query( array(), (object) array( 'context' => array( 'queryId' => KOGO_CURRENT_EXHIBITIONS_QUERY_ID ) ) );
$past    = kogo_filter_exhibition_archive_query( array(), (object) array( 'context' => array( 'queryId' => KOGO_PAST_EXHIBITIONS_QUERY_ID ) ) );

if ( '<=' !== $current['meta_query']['opening_date']['compare'] || '>=' !== $current['meta_query']['closing_date']['compare'] || array( 'closing_date' => 'ASC' ) !== $current['orderby'] ) {
	fwrite( STDERR, "Current exhibitions query is incorrect.\n" );
	exit( 1 );
}

if ( '<' !== $past['meta_query']['closing_date']['compare'] || array( 'closing_date' => 'DESC' ) !== $past['orderby'] ) {
	fwrite( STDERR, "Past exhibitions query is incorrect.\n" );
	exit( 1 );
}

$first_context  = (object) array( 'context' => array( 'postId' => 101 ) );
$second_context = (object) array( 'context' => array( 'postId' => 102 ) );
$dates_block    = array( 'innerHTML' => '[kogo_exhibition_dates]' );
$artists_block  = array( 'innerHTML' => '[kogo_exhibition_artists]' );

$stale_dates  = '<p><span class="kogo-exhibition-card__dates">31.03.2023–20.05.2023</span></p>';
$first_dates  = kogo_render_exhibition_shortcode_block( $stale_dates, $dates_block, $first_context );
$second_dates = kogo_render_exhibition_shortcode_block( $stale_dates, $dates_block, $second_context );

if ( false === strpos( $first_dates, '12.02.2026–11.04.2026' ) || false === strpos( $second_dates, '15.08.2025–27.09.2025' ) ) {
	fwrite( STDERR, "Exhibition dates do not follow the Query Loop post context.\n" );
	exit( 1 );
}

$stale_artists  = '<p><span class="kogo-exhibition-card__artists">Kristina Õllek</span></p>';
$first_artists  = kogo_render_exhibition_shortcode_block( $stale_artists, $artists_block, $first_context );
$second_artists = kogo_render_exhibition_shortcode_block( $stale_artists, $artists_block, $second_context );

if ( false === strpos( $first_artists, 'Sabine Vernere' ) || false === strpos( $second_artists, 'Paweł Matyszewski' ) ) {
	fwrite( STDERR, "Exhibition artists do not follow the Query Loop post context.\n" );
	exit( 1 );
}

echo "Kogo exhibition archive test passed.\n";
