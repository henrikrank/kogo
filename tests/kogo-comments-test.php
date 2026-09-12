<?php
/** Run with: php tests/kogo-comments-test.php */

define( 'ABSPATH', __DIR__ . '/' );
$filters  = array();
$supports = array_fill_keys( array( 'post', 'page', 'attachment', 'kogo_exposition', 'kogo_artist', 'kogo_work' ), array( 'comments', 'trackbacks', 'title', 'editor' ) );
function add_filter( $hook, $callback ) { global $filters; $filters[ $hook ] = $callback; }
function add_action() {}
function __return_false() { return false; }
function __return_empty_array() { return array(); }
function __return_zero() { return 0; }
function __( $text ) { return $text; }
class WP_Error {}
function get_post_types() { global $supports; return array_keys( $supports ); }
function remove_post_type_support( $type, $feature ) { global $supports; $supports[ $type ] = array_values( array_diff( $supports[ $type ], array( $feature ) ) ); }

require dirname( __DIR__ ) . '/themes/kogo/inc/comments.php';

function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

foreach ( array( 'comments_open', 'pings_open', 'feed_links_show_comments_feed' ) as $hook ) {
	check( false === $filters[ $hook ]( true ), 'Comments, pingbacks, and feed links must be disabled.' );
}
foreach ( array( '', 'comment', 'pingback', 'trackback' ) as $type ) {
	check( kogo_reject_public_comments( 1, array( 'comment_type' => $type ) ) instanceof WP_Error, 'Block ordinary comments through the approval pipeline, including admin replies.' );
}
check( 1 === kogo_reject_public_comments( 1, array( 'comment_type' => 'note' ) ), 'Preserve internal editorial notes.' );
check( array() === $filters['comments_array']( array( 'Existing comment' ) ) && 0 === $filters['get_comments_number']( 1 ), 'Hide existing comments and counts without deleting data.' );
foreach ( array( 'pre_option_default_comment_status', 'pre_option_default_ping_status' ) as $hook ) {
	check( 'closed' === $filters[ $hook ](), 'New content must default to closed comments and pings.' );
}
$data = $filters['wp_insert_post_data']( array( 'post_title' => 'Preserved title', 'comment_status' => 'open', 'ping_status' => 'open' ) );
check( 'closed' === $data['comment_status'] && 'closed' === $data['ping_status'] && 'Preserved title' === $data['post_title'], 'Every save must close comments without changing content.' );
kogo_remove_comment_support();
foreach ( $supports as $features ) {
	check( array( 'title', 'editor' ) === $features, 'Remove only comments and trackbacks from every post type.' );
}
foreach ( array( 'core/comments', 'core/comment-template', 'core/comment-content', 'core/post-comments', 'core/post-comments-form', 'core/post-comments-link', 'core/post-comments-count' ) as $name ) {
	check( '' === kogo_hide_comment_blocks( '<div>Comment UI</div>', array( 'blockName' => $name ) ), 'Hide all comment blocks and forms, including legacy blocks.' );
}
check( '<p>News</p>' === kogo_hide_comment_blocks( '<p>News</p>', array( 'blockName' => 'core/paragraph' ) ), 'Leave unrelated front-end blocks intact.' );
check( array( 'title' => 'Title', 'date' => 'Date' ) === kogo_remove_comment_column( array( 'title' => 'Title', 'comments' => 'Comments', 'date' => 'Date' ) ), 'Remove only the comment column.' );

echo "Kogo comments test passed.\n";
