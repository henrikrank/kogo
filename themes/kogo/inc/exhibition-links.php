<?php
/**
 * Editorial exhibition links and their shared artist-grid presentation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KOGO_LINKED_EXHIBITIONS_META     = 'kogo_linked_exhibition_post_ids';
const KOGO_LINKED_EXHIBITIONS_QUERY_ID = 90405;

/** Return unique linked WordPress post IDs, excluding the exhibition itself. */
function kogo_get_linked_exhibition_ids( $post_id ) {
	$ids = get_post_meta( $post_id, KOGO_LINKED_EXHIBITIONS_META, true );
	return array_values( array_diff( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ), array( (int) $post_id ) ) );
}

function kogo_add_linked_exhibitions_column( $columns ) {
	$result = array();
	foreach ( $columns as $key => $label ) {
		$result[ $key ] = $label;
		if ( 'title' === $key ) {
			$result['kogo_linked_exhibitions'] = __( 'Linked Exhibitions', 'kogo' );
		}
	}
	return $result;
}
add_filter( 'manage_kogo_exposition_posts_columns', 'kogo_add_linked_exhibitions_column', 20 );

function kogo_render_linked_exhibitions_column( $column, $post_id ) {
	if ( 'kogo_linked_exhibitions' !== $column ) {
		return;
	}

	$titles = array();
	foreach ( kogo_get_linked_exhibition_ids( $post_id ) as $linked_id ) {
		if ( 'kogo_exposition' !== get_post_type( $linked_id ) || 'trash' === get_post_status( $linked_id ) ) {
			continue;
		}
		$title    = esc_html( get_the_title( $linked_id ) );
		$edit_url = get_edit_post_link( $linked_id );
		$titles[] = $edit_url ? '<a href="' . esc_url( $edit_url ) . '">' . $title . '</a>' : $title;
	}
	echo $titles ? implode( '<br>', $titles ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'manage_kogo_exposition_posts_custom_column', 'kogo_render_linked_exhibitions_column', 10, 2 );

/** Keep the column available in Screen Options, respecting saved preferences. */
function kogo_default_hidden_exhibition_columns( $hidden, $screen ) {
	if ( 'edit-kogo_exposition' === $screen->id ) {
		$hidden[] = 'kogo_itgallery_modified';
	}
	return array_values( array_unique( $hidden ) );
}
add_filter( 'default_hidden_columns', 'kogo_default_hidden_exhibition_columns', 10, 2 );

function kogo_add_exhibition_link_bulk_actions( $actions ) {
	$actions['kogo_link_exhibitions']   = __( 'Link Exhibitions', 'kogo' );
	$actions['kogo_unlink_exhibitions'] = __( 'Unlink Exhibitions', 'kogo' );
	return $actions;
}
add_filter( 'bulk_actions-edit-kogo_exposition', 'kogo_add_exhibition_link_bulk_actions' );

/**
 * Link the selected exhibitions to each other, or clear their connections.
 * WordPress verifies the bulk-posts nonce before invoking this filter.
 */
function kogo_handle_exhibition_link_bulk_action( $redirect, $action, $post_ids ) {
	if ( ! in_array( $action, array( 'kogo_link_exhibitions', 'kogo_unlink_exhibitions' ), true ) ) {
		return $redirect;
	}

	$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
	foreach ( $post_ids as $post_id ) {
		if ( 'kogo_exposition' !== get_post_type( $post_id ) || 'trash' === get_post_status( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You can only link or unlink exhibitions you are allowed to edit.', 'kogo' ) );
		}
	}

	$redirect = remove_query_arg( array( 'kogo_exhibitions_linked', 'kogo_exhibitions_unlinked', 'kogo_exhibitions_link_error' ), $redirect );
	if ( 'kogo_link_exhibitions' === $action && count( $post_ids ) < 2 ) {
		return add_query_arg( 'kogo_exhibitions_link_error', 1, $redirect );
	}

	// Check permissions for reciprocal links before changing any metadata.
	if ( 'kogo_unlink_exhibitions' === $action ) {
		foreach ( $post_ids as $post_id ) {
			foreach ( kogo_get_linked_exhibition_ids( $post_id ) as $linked_id ) {
				if ( 'kogo_exposition' === get_post_type( $linked_id ) && ! current_user_can( 'edit_post', $linked_id ) ) {
					wp_die( esc_html__( 'You are not allowed to update a linked exhibition.', 'kogo' ) );
				}
			}
		}
	}

	foreach ( $post_ids as $post_id ) {
		$linked_ids = kogo_get_linked_exhibition_ids( $post_id );
		if ( 'kogo_link_exhibitions' === $action ) {
			$linked_ids = array_values( array_diff( array_unique( array_merge( $linked_ids, $post_ids ) ), array( $post_id ) ) );
			update_post_meta( $post_id, KOGO_LINKED_EXHIBITIONS_META, $linked_ids );
		} else {
			foreach ( $linked_ids as $linked_id ) {
				if ( 'kogo_exposition' !== get_post_type( $linked_id ) ) {
					continue;
				}
				$remaining = array_values( array_diff( kogo_get_linked_exhibition_ids( $linked_id ), $post_ids ) );
				if ( $remaining ) {
					update_post_meta( $linked_id, KOGO_LINKED_EXHIBITIONS_META, $remaining );
				} else {
					delete_post_meta( $linked_id, KOGO_LINKED_EXHIBITIONS_META );
				}
			}
			delete_post_meta( $post_id, KOGO_LINKED_EXHIBITIONS_META );
		}
	}

	return add_query_arg( 'kogo_link_exhibitions' === $action ? 'kogo_exhibitions_linked' : 'kogo_exhibitions_unlinked', count( $post_ids ), $redirect );
}
add_filter( 'handle_bulk_actions-edit-kogo_exposition', 'kogo_handle_exhibition_link_bulk_action', 10, 3 );

function kogo_exhibition_link_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-kogo_exposition' !== $screen->id ) {
		return;
	}

	if ( isset( $_GET['kogo_exhibitions_link_error'] ) ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Select at least two exhibitions to link.', 'kogo' ) . '</p></div>';
		return;
	}

	foreach ( array( 'kogo_exhibitions_linked', 'kogo_exhibitions_unlinked' ) as $key ) {
		$count = isset( $_GET[ $key ] ) ? absint( $_GET[ $key ] ) : 0;
		if ( ! $count ) {
			continue;
		}
		$message = 'kogo_exhibitions_linked' === $key
			? _n( 'Links updated for %d exhibition.', 'Links updated for %d exhibitions.', $count, 'kogo' )
			: _n( 'Links cleared for %d exhibition.', 'Links cleared for %d exhibitions.', $count, 'kogo' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( $message, $count ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'kogo_exhibition_link_admin_notice' );

/** Scope the native Query Loop to published exhibitions in editorial order. */
function kogo_filter_linked_exhibitions_query( $query, $block ) {
	if ( KOGO_LINKED_EXHIBITIONS_QUERY_ID !== (int) ( $block->context['queryId'] ?? 0 ) ) {
		return $query;
	}

	$post_id                = get_queried_object_id();
	$query['post_type']     = 'kogo_exposition';
	$query['post_status']   = 'publish';
	$query['post__in']      = 'kogo_exposition' === get_post_type( $post_id ) ? ( kogo_get_linked_exhibition_ids( $post_id ) ?: array( 0 ) ) : array( 0 );
	$query['posts_per_page'] = -1;
	$query['orderby']       = 'post__in';
	$query['no_found_rows'] = true;
	return $query;
}
add_filter( 'query_loop_block_query_vars', 'kogo_filter_linked_exhibitions_query', 20, 2 );

/** Hide the entire section, including its rules, when no linked cards exist. */
function kogo_hide_empty_linked_exhibitions( $content, $block ) {
	if ( false !== strpos( $block['attrs']['className'] ?? '', 'kogo-linked-exhibitions-section' ) && false === strpos( $content, 'kogo-linked-exhibitions__card' ) ) {
		return '';
	}
	return $content;
}
add_filter( 'render_block_core/group', 'kogo_hide_empty_linked_exhibitions', 10, 2 );

/** Render artists and explicit curator credits with each card's post context. */
function kogo_render_linked_exhibition_credits( $post_id ) {
	$artists = wp_strip_all_tags( kogo_render_exhibition_artists( $post_id ) );
	$curator = kogo_get_exhibition_curator( $post_id );
	$credits = implode( '. ', array_filter( array( $artists, $curator ? sprintf( __( 'Curated by %s', 'kogo' ), $curator ) : '' ) ) );
	return $credits ? '<div class="kogo-artists__disciplines">' . esc_html( html_entity_decode( $credits, ENT_QUOTES, 'UTF-8' ) ) . '</div>' : '';
}

function kogo_linked_exhibition_credits_shortcode() {
	return kogo_render_linked_exhibition_credits( get_the_ID() );
}
add_shortcode( 'kogo_linked_exhibition_credits', 'kogo_linked_exhibition_credits_shortcode' );

function kogo_linked_exhibition_credits_block( $content, $block, $instance ) {
	if ( false !== strpos( ( $block['innerHTML'] ?? '' ) . $content, '[kogo_linked_exhibition_credits]' ) ) {
		return kogo_render_linked_exhibition_credits( (int) ( $instance->context['postId'] ?? get_the_ID() ) );
	}
	return $content;
}
add_filter( 'render_block_core/shortcode', 'kogo_linked_exhibition_credits_block', 10, 3 );
