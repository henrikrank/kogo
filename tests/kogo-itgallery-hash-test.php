<?php

define( 'ABSPATH', __DIR__ . '/' );

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function __( $text ) {
	return $text; }
function register_post_type() {}
function register_taxonomy( $taxonomy, $object_type, $args ) {
	$GLOBALS['kogo_test_taxonomy'] = compact( 'taxonomy', 'object_type', 'args' );
}
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags ); }
function esc_url_raw( $value ) {
	return $value;
}
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}
function get_post_meta( $post_id, $key ) {
	return isset( $GLOBALS['kogo_test_meta'][ $post_id ][ $key ] ) ? $GLOBALS['kogo_test_meta'][ $post_id ][ $key ] : '';
}
function get_post_status() {
	return 'publish'; }
function current_user_can() { return $GLOBALS['kogo_test_admin'] ?? true; }
function get_option() { return $GLOBALS['kogo_test_last_sync'] ?? false; }
function admin_url( $path ) { return '/wp-admin/' . $path; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function wp_nonce_field( $action, $name, $referer, $echo ) {
	return '<input type="hidden" name="' . $name . '" value="test-nonce">';
}

require dirname( __DIR__ ) . '/themes/kogo/inc/kogo-itgallery.php';
require dirname( __DIR__ ) . '/themes/kogo/inc/artist-grid.php';

Kogo_ITGallery::register_post_types();
if ( 'kogo_artist_category' !== $GLOBALS['kogo_test_taxonomy']['taxonomy'] || 'kogo_artist' !== $GLOBALS['kogo_test_taxonomy']['object_type'] || empty( $GLOBALS['kogo_test_taxonomy']['args']['hierarchical'] ) || empty( $GLOBALS['kogo_test_taxonomy']['args']['show_admin_column'] ) ) {
	fwrite( STDERR, "Artist categories were not registered as a hierarchical admin taxonomy.\n" );
	exit( 1 );
}

$original                   = array(
	'id'     => 42,
	'name'   => 'A Work',
	'artist' => array(
		'name' => 'Ada',
		'id'   => 7,
	),
	'images' => array( array( 'url' => 'one' ), array( 'url' => 'two' ) ),
);
$same                       = array(
	'images' => array( array( 'url' => 'one' ), array( 'url' => 'two' ) ),
	'artist' => array(
		'id'   => 7,
		'name' => 'Ada',
	),
	'name'   => 'A Work',
	'id'     => 42,
);
$changed                    = $same;
$changed['name']            = 'A Changed Work';
$reordered_images           = $same;
$reordered_images['images'] = array_reverse( $reordered_images['images'] );

$hash = Kogo_ITGallery::fingerprint( 'work', $original );

if ( $hash !== Kogo_ITGallery::fingerprint( 'work', $same ) ) {
	fwrite( STDERR, "Associative key order changed the fingerprint.\n" );
	exit( 1 );
}
if ( $hash === Kogo_ITGallery::fingerprint( 'work', $changed ) ) {
	fwrite( STDERR, "A meaningful value change did not change the fingerprint.\n" );
	exit( 1 );
}
if ( $hash === Kogo_ITGallery::fingerprint( 'work', $reordered_images ) ) {
	fwrite( STDERR, "List order did not change the fingerprint.\n" );
	exit( 1 );
}

$GLOBALS['kogo_test_meta'][99]['_kogo_itgallery_hash'] = $hash;
$plugin = new Kogo_ITGallery();
$toolbar = new class {
	public $nodes = array();
	public function add_node( $node ) { $this->nodes[ $node['id'] ] = $node; }
};
$plugin->add_admin_bar_menu( $toolbar );
if ( 4 !== count( $toolbar->nodes ) || 'Last sync: Never' !== $toolbar->nodes['kogo-itgallery-last-sync']['title'] || strpos( $toolbar->nodes['kogo-itgallery-sync']['title'], 'method="post"' ) === false || strpos( $toolbar->nodes['kogo-itgallery-sync']['title'], 'test-nonce' ) === false || '/wp-admin/admin.php?page=kogo-itgallery' !== $toolbar->nodes['kogo-itgallery-settings']['href'] ) {
	fwrite( STDERR, "ITGallery toolbar must provide sync status, a nonce-protected POST action, and Settings.\n" );
	exit( 1 );
}
$GLOBALS['kogo_test_last_sync'] = '2026-04-29 14:45:00';
$plugin->add_admin_bar_menu( $toolbar );
if ( 'Last sync: 29.04.2026 17:45' !== $toolbar->nodes['kogo-itgallery-last-sync']['title'] ) {
	fwrite( STDERR, "The toolbar last-sync timestamp must use Tallinn time.\n" );
	exit( 1 );
}
$toolbar->nodes = array();
$GLOBALS['kogo_test_admin'] = false;
$plugin->add_admin_bar_menu( $toolbar );
if ( $toolbar->nodes ) {
	fwrite( STDERR, "ITGallery sync and settings must remain administrator-only.\n" );
	exit( 1 );
}
$GLOBALS['kogo_test_admin'] = true;
$method = new ReflectionMethod( Kogo_ITGallery::class, 'sync_item' );
if ( PHP_VERSION_ID < 80100 ) {
	$method->setAccessible( true );
}
$result = $method->invoke( $plugin, 'work', $same, 99 );

if ( 'unchanged' !== $result['status'] || 99 !== $result['post_id'] ) {
	fwrite( STDERR, "An unchanged record did not take the no-write path.\n" );
	exit( 1 );
}

$date_method = new ReflectionMethod( Kogo_ITGallery::class, 'format_itgallery_datetime' );
if ( PHP_VERSION_ID < 80100 ) {
	$date_method->setAccessible( true );
}
if ( '29.04.2026 17:45' !== $date_method->invoke( null, '2026-04-29 14:45:00' ) ) {
	fwrite( STDERR, "ITGallery timestamps were not converted to Tallinn time.\n" );
	exit( 1 );
}

$GLOBALS['kogo_test_meta'][55]['_kogo_itgallery_images'] = array(
	array(
		'url'       => 'https://images.example/original.jpg',
		'small_url' => 'https://images.example/small.jpg',
	),
);
if ( 'https://images.example/small.jpg' !== kogo_itgallery_get_image_url( 55, 'small' ) ) {
	fwrite( STDERR, "The admin thumbnail did not prefer ITGallery's small image URL.\n" );
	exit( 1 );
}

$query = kogo_filter_artists_grid_query(
	array(
		'post_type'    => 'post',
		'category__in' => array( 12 ),
	),
	(object) array(
		'context'      => array(),
		'parsed_block' => array( 'attrs' => array( 'className' => 'kogo-artists__grid' ) ),
	)
);
if ( 'kogo_artist' !== $query['post_type'] || isset( $query['category__in'] ) ) {
	fwrite( STDERR, "The Artist Grid did not force the Artist post type.\n" );
	exit( 1 );
}

$GLOBALS['kogo_test_meta'][70]['_kogo_itgallery_work_post_ids'] = array( 71, 72, 73 );
$GLOBALS['kogo_test_meta'][71]['_kogo_itgallery_type']          = array( 'name' => 'Painting' );
$GLOBALS['kogo_test_meta'][72]['_kogo_itgallery_type']          = array( 'name' => 'Installation' );
$GLOBALS['kogo_test_meta'][73]['_kogo_itgallery_type']          = array( 'name' => 'Painting' );
if ( array( 'painting', 'installation' ) !== kogo_get_artist_disciplines( 70 ) ) {
	fwrite( STDERR, "Artist disciplines were not derived from linked ITGallery works.\n" );
	exit( 1 );
}

echo "Kogo ITGallery fingerprint test passed.\n";
