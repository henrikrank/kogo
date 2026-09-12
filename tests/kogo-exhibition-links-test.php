<?php
/** Run with: php tests/kogo-exhibition-links-test.php */

define( 'ABSPATH', __DIR__ . '/' );

$meta       = array();
$denied     = array();
$queried_id = 101;
$posts      = array(
	101 => array( 'type' => 'kogo_exposition', 'status' => 'publish', 'title' => 'First exhibition' ),
	102 => array( 'type' => 'kogo_exposition', 'status' => 'publish', 'title' => 'Second & exhibition' ),
	103 => array( 'type' => 'kogo_exposition', 'status' => 'publish', 'title' => 'Third exhibition' ),
	104 => array( 'type' => 'kogo_exposition', 'status' => 'draft', 'title' => 'Draft exhibition' ),
	105 => array( 'type' => 'kogo_exposition', 'status' => 'trash', 'title' => 'Trashed exhibition' ),
	201 => array( 'type' => 'kogo_artist', 'status' => 'publish', 'title' => 'An artist' ),
);

function add_action() {}
function add_filter() {}
function add_shortcode() {}
function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function get_post_meta( $id, $key ) { global $meta; return $meta[ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { global $meta; $meta[ $id ][ $key ] = $value; }
function delete_post_meta( $id, $key ) { global $meta; unset( $meta[ $id ][ $key ] ); }
function get_post_type( $id ) { global $posts; return $posts[ $id ]['type'] ?? false; }
function get_post_status( $id ) { global $posts; return $posts[ $id ]['status'] ?? false; }
function get_the_title( $id ) { global $posts; return $posts[ $id ]['title'] ?? ''; }
function get_edit_post_link( $id ) { return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
function current_user_can( $cap, $id ) { global $denied; return ! in_array( $id, $denied, true ); }
function get_queried_object_id() { global $queried_id; return $queried_id; }
function get_the_ID() { return 101; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function kogo_render_exhibition_artists( $id ) { return '<span>Artist &amp; ' . $id . '</span>'; }
function kogo_get_exhibition_curator( $id ) { return 'Curator ' . $id; }
function remove_query_arg( $keys, $url ) { return $url; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }

require dirname( __DIR__ ) . '/themes/kogo/inc/exhibition-links.php';

function check( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$actions = kogo_add_exhibition_link_bulk_actions( array( 'edit' => 'Edit' ) );
check( isset( $actions['edit'], $actions['kogo_link_exhibitions'], $actions['kogo_unlink_exhibitions'] ), 'Keep native bulk actions and add both link actions.' );
$columns = kogo_add_linked_exhibitions_column( array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ) );
check( array( 'cb', 'title', 'kogo_linked_exhibitions', 'date' ) === array_keys( $columns ), 'Place linked exhibition titles after Title.' );
check( array( 'existing', 'kogo_itgallery_modified' ) === kogo_default_hidden_exhibition_columns( array( 'existing' ), (object) array( 'id' => 'edit-kogo_exposition' ) ), 'Hide only the ITGallery update column by default.' );
check( array() === kogo_default_hidden_exhibition_columns( array(), (object) array( 'id' => 'edit-kogo_artist' ) ), 'Leave other admin lists unchanged.' );
check( '/admin' === kogo_handle_exhibition_link_bulk_action( '/admin', 'edit', array( 101, 102 ) ), 'Leave unrelated bulk actions untouched.' );

$redirect = kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_link_exhibitions', array( 101 ) );
check( false !== strpos( $redirect, 'kogo_exhibitions_link_error=1' ) && ! $meta, 'A single selection must not create links.' );
kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_link_exhibitions', array( 101, '102', 103, 102, 0 ) );
check( array( 102, 103 ) === kogo_get_linked_exhibition_ids( 101 ) && array( 101, 103 ) === kogo_get_linked_exhibition_ids( 102 ) && array( 101, 102 ) === kogo_get_linked_exhibition_ids( 103 ), 'Link every selected exhibition mutually with integer IDs, without duplicates or self-links.' );
kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_link_exhibitions', array( 101, 104 ) );
check( array( 102, 103, 104 ) === kogo_get_linked_exhibition_ids( 101 ) && array( 101 ) === kogo_get_linked_exhibition_ids( 104 ), 'Add links while preserving existing connections; drafts may be linked editorially.' );
kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_link_exhibitions', array( 101, 104 ) );
check( array( 102, 103, 104 ) === kogo_get_linked_exhibition_ids( 101 ), 'Repeating Link must not duplicate connections.' );

$before = $meta;
foreach ( array( array( 101, 201 ), array( 101, 105 ), array( 101, 999 ) ) as $invalid ) {
	try {
		kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_link_exhibitions', $invalid );
		check( false, 'Reject non-exhibitions, trash, and missing posts.' );
	} catch ( RuntimeException $error ) {
		check( $before === $meta, 'Invalid selections must not partially modify links.' );
	}
}
$denied = array( 102 );
try {
	kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_link_exhibitions', array( 101, 102 ) );
	check( false, 'Reject selections the user cannot edit.' );
} catch ( RuntimeException $error ) {
	check( $before === $meta, 'Permission checks must precede writes.' );
}
try {
	kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_unlink_exhibitions', array( 101 ) );
	check( false, 'Require permission to remove reciprocal links.' );
} catch ( RuntimeException $error ) {
	check( $before === $meta, 'Reciprocal permission failure must not partially unlink posts.' );
}
$denied = array();

$query = kogo_filter_linked_exhibitions_query( array( 'post_type' => 'kogo_artist' ), (object) array( 'context' => array( 'queryId' => 90405 ) ) );
check( 'kogo_exposition' === $query['post_type'] && 'publish' === $query['post_status'] && array( 102, 103, 104 ) === $query['post__in'] && 'post__in' === $query['orderby'] && -1 === $query['posts_per_page'], 'Query only published linked exhibitions, retaining meta order and all cards.' );
check( array( 'unchanged' => true ) === kogo_filter_linked_exhibitions_query( array( 'unchanged' => true ), (object) array( 'context' => array( 'queryId' => 90403 ) ) ), 'Leave the homepage artist query untouched.' );
$queried_id = 201;
check( array( 0 ) === kogo_filter_linked_exhibitions_query( array(), (object) array( 'context' => array( 'queryId' => 90405 ) ) )['post__in'], 'Avoid querying all exhibitions outside an exhibition page.' );
$queried_id = 101;
$block      = array( 'attrs' => array( 'className' => 'kogo-artists kogo-linked-exhibitions-section' ) );
check( '' === kogo_hide_empty_linked_exhibitions( '<section><h2>Related exhibitions</h2></section>', $block ), 'An empty section must leave no heading, button, or border.' );
$card = '<section><div class="kogo-linked-exhibitions__card">Card</div></section>';
check( $card === kogo_hide_empty_linked_exhibitions( $card, $block ), 'Keep the section when a linked card renders.' );
$credits = kogo_linked_exhibition_credits_block( '[kogo_linked_exhibition_credits]', array(), (object) array( 'context' => array( 'postId' => 102 ) ) );
check( false !== strpos( $credits, 'Artist &amp; 102. Curated by Curator 102' ) && false === strpos( $credits, '101' ), 'Render each linked card\'s credits in its own post context, escaping names once.' );
check( 'hero' === kogo_linked_exhibition_credits_block( 'hero', array(), (object) array( 'context' => array() ) ), 'Do not alter other shortcode blocks.' );

$meta[102][KOGO_LINKED_EXHIBITIONS_META][] = 105;
ob_start();
kogo_render_linked_exhibitions_column( 'kogo_linked_exhibitions', 102 );
$column = ob_get_clean();
check( false !== strpos( $column, 'First exhibition' ) && false !== strpos( $column, 'Third exhibition' ) && false === strpos( $column, 'Trashed exhibition' ), 'Admin column must display linked titles and omit trash.' );
ob_start();
kogo_render_linked_exhibitions_column( 'kogo_linked_exhibitions', 101 );
check( false !== strpos( ob_get_clean(), 'Second &amp; exhibition' ), 'Escape exhibition titles in admin links.' );

kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_unlink_exhibitions', array( 101 ) );
check( ! isset( $meta[101][KOGO_LINKED_EXHIBITIONS_META] ) && array( 103, 105 ) === kogo_get_linked_exhibition_ids( 102 ) && array( 102 ) === kogo_get_linked_exhibition_ids( 103 ) && array() === kogo_get_linked_exhibition_ids( 104 ), 'Unlink clears selected meta and reciprocal links while preserving other connections.' );
kogo_handle_exhibition_link_bulk_action( '/admin', 'kogo_unlink_exhibitions', array( 102, 103 ) );
check( ! isset( $meta[102][KOGO_LINKED_EXHIBITIONS_META] ) && ! isset( $meta[103][KOGO_LINKED_EXHIBITIONS_META] ), 'Unlink a group must remove metadata for every selection.' );
check( array( 0 ) === kogo_filter_linked_exhibitions_query( array(), (object) array( 'context' => array( 'queryId' => 90405 ) ) )['post__in'], 'Empty links must never fall back to an unrestricted exhibition query.' );

$template = file_get_contents( dirname( __DIR__ ) . '/themes/kogo/templates/single-kogo_exposition.html' );
check( strpos( $template, 'kogo/linked-exhibitions' ) > strpos( $template, '[kogo_exhibition_team]' ) && strpos( $template, 'kogo/linked-exhibitions' ) < strpos( $template, '</main>' ), 'Related exhibitions must be the final block before the footer.' );

echo "Kogo exhibition links test passed.\n";
