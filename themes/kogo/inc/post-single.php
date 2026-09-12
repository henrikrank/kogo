<?php
/**
 * Single post helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KOGO_RELATED_NEWS_QUERY_ID = 90404;

/** Use News throughout the built-in post type's admin interface. */
function kogo_news_post_labels( $labels ) {
	$news_labels = array(
		'name'                     => __( 'News', 'kogo' ),
		'singular_name'            => __( 'News item', 'kogo' ),
		'menu_name'                => __( 'News', 'kogo' ),
		'name_admin_bar'           => __( 'News item', 'kogo' ),
		'add_new'                  => __( 'Add News Item', 'kogo' ),
		'add_new_item'             => __( 'Add News Item', 'kogo' ),
		'edit_item'                => __( 'Edit News Item', 'kogo' ),
		'new_item'                 => __( 'New News Item', 'kogo' ),
		'view_item'                => __( 'View News Item', 'kogo' ),
		'view_items'               => __( 'View News', 'kogo' ),
		'search_items'             => __( 'Search News', 'kogo' ),
		'not_found'                => __( 'No news found.', 'kogo' ),
		'not_found_in_trash'       => __( 'No news found in Trash.', 'kogo' ),
		'all_items'                => __( 'All News', 'kogo' ),
		'archives'                 => __( 'News Archives', 'kogo' ),
		'attributes'               => __( 'News Attributes', 'kogo' ),
		'insert_into_item'         => __( 'Insert into news item', 'kogo' ),
		'uploaded_to_this_item'    => __( 'Uploaded to this news item', 'kogo' ),
		'filter_items_list'        => __( 'Filter news list', 'kogo' ),
		'items_list_navigation'    => __( 'News list navigation', 'kogo' ),
		'items_list'               => __( 'News list', 'kogo' ),
		'item_published'           => __( 'News item published.', 'kogo' ),
		'item_published_privately' => __( 'News item published privately.', 'kogo' ),
		'item_reverted_to_draft'   => __( 'News item reverted to draft.', 'kogo' ),
		'item_trashed'             => __( 'News item trashed.', 'kogo' ),
		'item_scheduled'           => __( 'News item scheduled.', 'kogo' ),
		'item_updated'             => __( 'News item updated.', 'kogo' ),
		'item_link'                => __( 'News item link', 'kogo' ),
		'item_link_description'    => __( 'A link to a news item.', 'kogo' ),
	);
	foreach ( $news_labels as $key => $label ) {
		$labels->$key = $label;
	}
	return $labels;
}
add_filter( 'post_type_labels_post', 'kogo_news_post_labels' );

/**
 * Show posts that share a category with the current article, excluding it.
 *
 * @param array    $query Query arguments.
 * @param WP_Block $block Query block.
 * @return array
 */
function kogo_filter_related_news_query( $query, $block ) {
	$query_id = isset( $block->context['queryId'] ) ? (int) $block->context['queryId'] : 0;
	$post_id  = get_queried_object_id();

	if ( KOGO_RELATED_NEWS_QUERY_ID !== $query_id || ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return $query;
	}

	$query['post__not_in'] = array_values( array_unique( array_merge( $query['post__not_in'] ?? array(), array( $post_id ) ) ) );
	$category_ids          = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );

	if ( $category_ids ) {
		$query['category__in'] = $category_ids;
	}

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'kogo_filter_related_news_query', 10, 2 );

/**
 * Remove the related-news section, including its separators, when it has no cards.
 *
 * @param string $block_content Rendered Group block markup.
 * @param array  $block         Parsed Group block.
 * @return string
 */
function kogo_hide_empty_related_news_section( $block_content, $block ) {
	$class_name = $block['attrs']['className'] ?? '';

	if ( false !== strpos( $class_name, 'kogo-related-news-section' ) && false === strpos( $block_content, 'kogo-posts-slider__card' ) ) {
		return '';
	}

	return $block_content;
}
add_filter( 'render_block_core/group', 'kogo_hide_empty_related_news_section', 10, 2 );
