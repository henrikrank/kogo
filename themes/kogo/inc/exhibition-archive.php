<?php
/**
 * Exhibition archive helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KOGO_CURRENT_EXHIBITIONS_QUERY_ID = 90401;
const KOGO_PAST_EXHIBITIONS_QUERY_ID    = 90402;

/**
 * Add date filtering and closing-date ordering to the archive Query blocks.
 *
 * @param array    $query Query arguments.
 * @param WP_Block $block Query child block.
 * @return array
 */
function kogo_filter_exhibition_archive_query( $query, $block ) {
	$query_id = isset( $block->context['queryId'] ) ? (int) $block->context['queryId'] : 0;

	if ( ! in_array( $query_id, array( KOGO_CURRENT_EXHIBITIONS_QUERY_ID, KOGO_PAST_EXHIBITIONS_QUERY_ID ), true ) ) {
		return $query;
	}

	$today              = wp_date( 'Y-m-d' );
	$query['post_type'] = 'kogo_exposition';

	if ( KOGO_CURRENT_EXHIBITIONS_QUERY_ID === $query_id ) {
		$query['posts_per_page'] = 4;
		$query['meta_query']     = array(
			'relation'           => 'AND',
			'opening_date'       => array(
				'key'     => '_kogo_itgallery_opening_date',
				'value'   => $today,
				'compare' => '<=',
				'type'    => 'DATE',
			),
			'closing_date'       => array(
				'key'     => '_kogo_itgallery_closing_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);
		$query['orderby']       = array( 'closing_date' => 'ASC' );
		$query['no_found_rows'] = true;
	} else {
		$query['meta_query'] = array(
			'closing_date' => array(
				'key'     => '_kogo_itgallery_closing_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			),
		);
		$query['orderby']   = array( 'closing_date' => 'DESC' );
	}

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'kogo_filter_exhibition_archive_query', 10, 2 );

/**
 * Format an imported exhibition date for display.
 *
 * @param string $date Date value.
 * @return string
 */
function kogo_format_exhibition_date( $date ) {
	$timestamp = strtotime( (string) $date );
	return $timestamp ? wp_date( 'd.m.Y', $timestamp ) : '';
}

/**
 * Render an exhibition's date range.
 *
 * @param int $post_id Exhibition post ID.
 * @return string
 */
function kogo_render_exhibition_dates( $post_id ) {
	$start   = kogo_format_exhibition_date( get_post_meta( $post_id, '_kogo_itgallery_opening_date', true ) );
	$end     = kogo_format_exhibition_date( get_post_meta( $post_id, '_kogo_itgallery_closing_date', true ) );

	$date_range = implode( '–', array_filter( array( $start, $end ) ) );
	return $date_range ? '<span class="kogo-exhibition-card__dates">' . esc_html( $date_range ) . '</span>' : '';
}

/**
 * Render the current exhibition's date range outside a contextual block.
 *
 * @return string
 */
function kogo_exhibition_dates_shortcode() {
	return kogo_render_exhibition_dates( get_the_ID() );
}
add_shortcode( 'kogo_exhibition_dates', 'kogo_exhibition_dates_shortcode' );

/**
 * Render imported artist names for an exhibition.
 *
 * @param int $post_id Exhibition post ID.
 * @return string
 */
function kogo_render_exhibition_artists( $post_id ) {
	$payload = get_post_meta( $post_id, '_kogo_itgallery_payload', true );
	$artists = is_array( $payload ) && isset( $payload['artists'] ) && is_array( $payload['artists'] ) ? $payload['artists'] : array();
	$names   = array();

	foreach ( $artists as $artist ) {
		if ( is_string( $artist ) ) {
			$name = $artist;
		} elseif ( is_array( $artist ) ) {
			$name = $artist['display_name'] ?? $artist['title'] ?? trim( ( $artist['name'] ?? $artist['first_name'] ?? '' ) . ' ' . ( $artist['surname'] ?? $artist['last_name'] ?? '' ) );
		} else {
			$name = '';
		}

		if ( $name ) {
			$names[] = sanitize_text_field( $name );
		}
	}

	if ( ! $names ) {
		$artist_post_ids = get_post_meta( $post_id, '_kogo_itgallery_artist_post_ids', true );
		foreach ( is_array( $artist_post_ids ) ? $artist_post_ids : array() as $artist_post_id ) {
			$names[] = get_the_title( $artist_post_id );
		}
	}

	$artist_names = implode( ', ', array_filter( array_unique( $names ) ) );
	return $artist_names ? '<span class="kogo-exhibition-card__artists">' . esc_html( $artist_names ) . '</span>' : '';
}

/**
 * Render imported artist names outside a contextual block.
 *
 * @return string
 */
function kogo_exhibition_artists_shortcode() {
	return kogo_render_exhibition_artists( get_the_ID() );
}
add_shortcode( 'kogo_exhibition_artists', 'kogo_exhibition_artists_shortcode' );

/**
 * Render exhibition shortcodes with the Query Loop card's post context.
 *
 * Core post blocks use this context already; relying on the global post here can
 * make every card display metadata from the same exhibition on the front end.
 *
 * @param string   $block_content Rendered shortcode block.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance.
 * @return string
 */
function kogo_render_exhibition_shortcode_block( $block_content, $block, $instance ) {
	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();

	if ( false !== strpos( $block_content, '[kogo_exhibition_dates]' ) || false !== strpos( $block_content, 'kogo-exhibition-card__dates' ) ) {
		return kogo_render_exhibition_dates( $post_id );
	}

	if ( false !== strpos( $block_content, '[kogo_exhibition_artists]' ) || false !== strpos( $block_content, 'kogo-exhibition-card__artists' ) ) {
		return kogo_render_exhibition_artists( $post_id );
	}

	return $block_content;
}
add_filter( 'render_block_core/shortcode', 'kogo_render_exhibition_shortcode_block', 10, 3 );

/**
 * Use the imported remote image when an exhibition has no local featured image.
 *
 * @param string   $block_content Rendered block.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance.
 * @return string
 */
function kogo_exhibition_featured_image_fallback( $block_content, $block, $instance ) {
	$class_name = $block['attrs']['className'] ?? '';

	if ( $block_content || false === strpos( $class_name, 'kogo-exhibition-card__image' ) ) {
		return $block_content;
	}

	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();
	if ( 'kogo_exposition' !== get_post_type( $post_id ) ) {
		return $block_content;
	}

	$image_url = kogo_itgallery_get_image_url( $post_id, 'large' );
	if ( ! $image_url ) {
		return $block_content;
	}

	return sprintf(
		'<figure class="wp-block-post-featured-image %1$s"><a href="%2$s"><img src="%3$s" alt="%4$s" loading="lazy" decoding="async"></a></figure>',
		esc_attr( $class_name ),
		esc_url( get_permalink( $post_id ) ),
		esc_url( $image_url ),
		esc_attr( get_the_title( $post_id ) )
	);
}
add_filter( 'render_block_core/post-featured-image', 'kogo_exhibition_featured_image_fallback', 10, 3 );
