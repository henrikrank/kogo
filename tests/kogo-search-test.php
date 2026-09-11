<?php

define( 'ABSPATH', __DIR__ );

function __( $text ) {
	return $text;
}

function add_action() {}
function add_shortcode() {}
function is_admin() {
	return false;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}

function sanitize_title( $value ) {
	return sanitize_key( $value );
}

function wp_unslash( $value ) {
	return $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

final class Kogo_Search_Test_Query {
	public $values = array();

	public function is_main_query() {
		return true;
	}

	public function is_search() {
		return true;
	}

	public function set( $key, $value ) {
		$this->values[ $key ] = $value;
	}
}

require dirname( __DIR__ ) . '/themes/kogo/inc/search.php';

$highlighted = kogo_highlight_search_term( 'Kogo & friends welcome KOGO.', 'kogo' );
if ( '<mark>Kogo</mark> &amp; friends welcome <mark>KOGO</mark>.' !== $highlighted ) {
	fwrite( STDERR, "Search highlighting is incorrect.\n" );
	exit( 1 );
}

$_GET  = array(
	'content_type' => 'kogo_work',
	'search_tag'   => 'new-work',
	'search_artist' => '27',
);
$query = new Kogo_Search_Test_Query();
kogo_filter_search_query( $query );

if ( 'kogo_work' !== $query->values['post_type'] || 10 !== $query->values['posts_per_page'] ) {
	fwrite( STDERR, "Search content filtering is incorrect.\n" );
	exit( 1 );
}

if ( 'new-work' !== $query->values['tax_query'][0]['terms'] || 'i:27;' !== $query->values['meta_query'][1]['value'] ) {
	fwrite( STDERR, "Search tag or artist filtering is incorrect.\n" );
	exit( 1 );
}

echo "Kogo search test passed.\n";
