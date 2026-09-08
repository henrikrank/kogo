<?php
/**
 * Artist Query Grid helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KOGO_FEATURED_ARTISTS_QUERY_ID = 90403;

/**
 * Keep existing and newly inserted Artist Query Grids on the Artist post type.
 *
 * @param array    $query Query arguments.
 * @param WP_Block $block Query block.
 * @return array
 */
function kogo_filter_artists_grid_query( $query, $block ) {
	$class_name = $block->parsed_block['attrs']['className'] ?? '';
	$query_id   = isset( $block->context['queryId'] ) ? (int) $block->context['queryId'] : 0;

	if ( false === strpos( $class_name, 'kogo-artists__grid' ) && KOGO_FEATURED_ARTISTS_QUERY_ID !== $query_id ) {
		return $query;
	}

	$query['post_type'] = 'kogo_artist';
	unset( $query['cat'], $query['category__in'], $query['category__and'], $query['category__not_in'], $query['category_name'] );

	if ( KOGO_FEATURED_ARTISTS_QUERY_ID === $query_id ) {
		$query['tax_query'] = array(
			array(
				'taxonomy' => 'kogo_artist_category',
				'field'    => 'slug',
				'terms'    => array( 'featured' ),
			),
		);
	}

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'kogo_filter_artists_grid_query', 10, 2 );

/**
 * Group artists by the opening year of their linked exhibitions.
 *
 * @return array
 */
function kogo_get_exhibited_artists_by_year() {
	$artist_ids = get_posts(
		array(
			'post_type'      => 'kogo_artist',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$years      = array();

	foreach ( $artist_ids as $artist_id ) {
		$exhibition_ids = get_post_meta( $artist_id, '_kogo_itgallery_exposition_post_ids', true );

		foreach ( is_array( $exhibition_ids ) ? $exhibition_ids : array() as $exhibition_id ) {
			$date = get_post_meta( (int) $exhibition_id, '_kogo_itgallery_opening_date', true );
			if ( ! preg_match( '/^(\d{4})/', (string) $date, $matches ) ) {
				continue;
			}

			$year                         = (int) $matches[1];
			$years[ $year ][ $artist_id ] = array(
				'name' => get_the_title( $artist_id ),
				'url'  => get_permalink( $artist_id ),
			);
		}
	}

	krsort( $years, SORT_NUMERIC );
	return $years;
}

/**
 * Render the exhibited-artist year index.
 *
 * @return string
 */
function kogo_exhibited_artists_shortcode() {
	$years = kogo_get_exhibited_artists_by_year();
	if ( ! $years ) {
		return '<p class="kogo-exhibited-artists__empty">' . esc_html__( 'No exhibited artists found.', 'kogo' ) . '</p>';
	}

	$html = '<div class="kogo-exhibited-artists__years">';
	foreach ( $years as $year => $artists ) {
		$html .= '<section class="kogo-exhibited-artists__year">';
		$html .= '<h3 class="kogo-exhibited-artists__year-heading">' . esc_html( $year ) . '</h3>';
		$html .= '<ul class="kogo-exhibited-artists__list">';
		foreach ( $artists as $artist ) {
			$html .= '<li><a href="' . esc_url( $artist['url'] ) . '">' . esc_html( $artist['name'] ) . '</a></li>';
		}
		$html .= '</ul></section>';
	}
	$html .= '</div>';

	return $html;
}
add_shortcode( 'kogo_exhibited_artists', 'kogo_exhibited_artists_shortcode' );

/**
 * Render artist shortcodes inside theme patterns.
 *
 * Block templates render patterns after the normal shortcode pass, so these
 * values need the same contextual rendering used by exhibition cards.
 *
 * @param string   $block_content Rendered shortcode block.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance.
 * @return string
 */
function kogo_render_artist_shortcode_block( $block_content, $block, $instance ) {
	$source  = ( $block['innerHTML'] ?? '' ) . $block_content;
	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();

	if ( false !== strpos( $source, '[kogo_artist_disciplines]' ) ) {
		$disciplines = implode( ', ', kogo_get_artist_disciplines( $post_id ) );
		return $disciplines ? '<div class="wp-block-shortcode kogo-artists__disciplines">' . esc_html( $disciplines ) . '</div>' : '';
	}

	if ( false !== strpos( $source, '[kogo_exhibited_artists]' ) ) {
		return kogo_exhibited_artists_shortcode();
	}

	return $block_content;
}
add_filter( 'render_block_core/shortcode', 'kogo_render_artist_shortcode_block', 10, 3 );

/**
 * Collect an artist's disciplines from ITGallery metadata.
 *
 * @param int $post_id Artist post ID.
 * @return array
 */
function kogo_get_artist_disciplines( $post_id ) {
	$payload     = get_post_meta( $post_id, '_kogo_itgallery_payload', true );
	$payload     = is_array( $payload ) ? $payload : array();
	$disciplines = array();

	foreach ( array( 'disciplines', 'tags' ) as $key ) {
		if ( ! empty( $payload[ $key ] ) && is_array( $payload[ $key ] ) ) {
			foreach ( $payload[ $key ] as $value ) {
				$disciplines[] = is_array( $value ) ? ( $value['name'] ?? $value['text'] ?? '' ) : $value;
			}
		}
	}

	if ( ! $disciplines ) {
		$work_post_ids = get_post_meta( $post_id, '_kogo_itgallery_work_post_ids', true );
		foreach ( is_array( $work_post_ids ) ? $work_post_ids : array() as $work_post_id ) {
			$type = get_post_meta( $work_post_id, '_kogo_itgallery_type', true );
			if ( is_array( $type ) ) {
				$disciplines[] = $type['name'] ?? $type['text'] ?? '';
			} elseif ( is_string( $type ) ) {
				$disciplines[] = $type;
			}
		}
	}

	if ( ! $disciplines && ! empty( $payload['artist_type'] ) ) {
		$type          = $payload['artist_type'];
		$disciplines[] = is_array( $type ) ? ( $type['name'] ?? $type['text'] ?? '' ) : $type;
	}

	$disciplines = array_filter( array_map( 'sanitize_text_field', $disciplines ) );
	return array_values( array_unique( array_map( 'strtolower', $disciplines ) ) );
}

/**
 * Render an artist's ITGallery disciplines.
 *
 * @return string
 */
function kogo_artist_disciplines_shortcode() {
	return esc_html( implode( ', ', kogo_get_artist_disciplines( get_the_ID() ) ) );
}
add_shortcode( 'kogo_artist_disciplines', 'kogo_artist_disciplines_shortcode' );

/**
 * Replace old Post Excerpt blocks in existing Artist Grids with disciplines.
 *
 * @param string   $block_content Rendered block.
 * @param array    $block Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function kogo_artist_disciplines_excerpt( $block_content, $block, $instance ) {
	$class_name = $block['attrs']['className'] ?? '';
	$post_id    = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();

	if ( false === strpos( $class_name, 'kogo-artists__disciplines' ) || 'kogo_artist' !== get_post_type( $post_id ) ) {
		return $block_content;
	}

	$disciplines = implode( ', ', kogo_get_artist_disciplines( $post_id ) );
	return $disciplines ? '<div class="wp-block-post-excerpt kogo-artists__disciplines"><p>' . esc_html( $disciplines ) . '</p></div>' : '';
}
add_filter( 'render_block_core/post-excerpt', 'kogo_artist_disciplines_excerpt', 10, 3 );

/**
 * Prefer the imported remote image, then retain the Media Library fallback.
 *
 * @param string   $block_content Rendered block.
 * @param array    $block Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function kogo_artist_remote_featured_image( $block_content, $block, $instance ) {
	$class_name = $block['attrs']['className'] ?? '';
	$post_id    = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : get_the_ID();

	if ( false === strpos( $class_name, 'kogo-artists__image' ) || 'kogo_artist' !== get_post_type( $post_id ) ) {
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
add_filter( 'render_block_core/post-featured-image', 'kogo_artist_remote_featured_image', 20, 3 );
