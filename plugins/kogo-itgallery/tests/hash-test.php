<?php

define( 'ABSPATH', __DIR__ . '/' );

function add_action() {}
function add_filter() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_basename( $file ) {
	return basename( $file ); }
function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags ); }
function get_post_meta( $post_id, $key ) {
	return isset( $GLOBALS['kogo_test_meta'][ $post_id ][ $key ] ) ? $GLOBALS['kogo_test_meta'][ $post_id ][ $key ] : '';
}
function get_post_status() {
	return 'publish'; }

require dirname( __DIR__ ) . '/kogo-itgallery.php';

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
$method = new ReflectionMethod( Kogo_ITGallery::class, 'sync_item' );
if ( PHP_VERSION_ID < 80100 ) {
	$method->setAccessible( true );
}
$result = $method->invoke( $plugin, 'work', $same, 99 );

if ( 'unchanged' !== $result['status'] || 99 !== $result['post_id'] ) {
	fwrite( STDERR, "An unchanged record did not take the no-write path.\n" );
	exit( 1 );
}

echo "Kogo ITGallery fingerprint test passed.\n";
