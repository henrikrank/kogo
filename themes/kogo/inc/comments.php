<?php
/** Disable public comments, trackbacks, and their interface throughout the site. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'get_comments_number', '__return_zero', 20 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );

/** Reject ordinary comments even when submitted through an admin reply action. */
function kogo_reject_public_comments( $approved, $comment ) {
	if ( in_array( $comment['comment_type'] ?? '', array( '', 'comment', 'pingback', 'trackback' ), true ) ) {
		return new WP_Error( 'comment_closed', __( 'Comments are disabled on this site.', 'kogo' ), array( 'status' => 403 ) );
	}
	return $approved;
}
add_filter( 'pre_comment_approved', 'kogo_reject_public_comments', 20, 2 );

function kogo_closed_comment_status() {
	return 'closed';
}
add_filter( 'pre_option_default_comment_status', 'kogo_closed_comment_status' );
add_filter( 'pre_option_default_ping_status', 'kogo_closed_comment_status' );

/** Store closed statuses on new posts and subsequent edits, including imports. */
function kogo_close_post_comments( $data ) {
	$data['comment_status'] = 'closed';
	$data['ping_status']    = 'closed';
	return $data;
}
add_filter( 'wp_insert_post_data', 'kogo_close_post_comments' );

function kogo_remove_comment_support() {
	foreach ( get_post_types() as $post_type ) {
		remove_post_type_support( $post_type, 'comments' );
		remove_post_type_support( $post_type, 'trackbacks' );
	}
}
add_action( 'init', 'kogo_remove_comment_support', 100 );

/** Hide current and legacy comment blocks, including links and forms. */
function kogo_hide_comment_blocks( $content, $block ) {
	$name = $block['blockName'] ?? '';
	if ( 0 === strpos( $name, 'core/comment' ) || 0 === strpos( $name, 'core/post-comments' ) ) {
		return '';
	}
	return $content;
}
add_filter( 'render_block', 'kogo_hide_comment_blocks', 20, 2 );

function kogo_remove_comment_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
	remove_submenu_page( 'options-general.php', 'options-discussion.php' );
}
add_action( 'admin_menu', 'kogo_remove_comment_admin_menu', 100 );

function kogo_remove_comment_meta_boxes() {
	foreach ( get_post_types() as $post_type ) {
		remove_meta_box( 'commentstatusdiv', $post_type, 'normal' );
		remove_meta_box( 'commentsdiv', $post_type, 'normal' );
		remove_meta_box( 'trackbacksdiv', $post_type, 'normal' );
	}
}
add_action( 'add_meta_boxes', 'kogo_remove_comment_meta_boxes', 100 );

/** The Activity and At a Glance widgets have no hook for their comment sections. */
function kogo_hide_dashboard_comments() {
	$screen = get_current_screen();
	if ( $screen && 'dashboard' === $screen->id ) {
		echo '<style>#latest-comments, .comment-count, .comment-mod-count { display: none !important; }</style>';
	}
}
add_action( 'admin_head', 'kogo_hide_dashboard_comments' );

function kogo_remove_comment_toolbar( $admin_bar ) {
	$admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'kogo_remove_comment_toolbar', 100 );

function kogo_remove_comment_column( $columns ) {
	unset( $columns['comments'] );
	return $columns;
}
add_filter( 'manage_posts_columns', 'kogo_remove_comment_column', 20 );
add_filter( 'manage_pages_columns', 'kogo_remove_comment_column', 20 );
