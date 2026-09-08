<?php
/**
 * Single post helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KOGO_RELATED_NEWS_QUERY_ID = 90404;

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
