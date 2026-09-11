<?php
/**
 * Search-page query filters and result markup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Searchable content types shown in the search filter.
 *
 * @return array<string, string>
 */
function kogo_search_content_types() {
	return array(
		'post'             => __( 'News', 'kogo' ),
		'page'             => __( 'Pages', 'kogo' ),
		'kogo_exposition'  => __( 'Exhibitions', 'kogo' ),
		'kogo_artist'      => __( 'Artists', 'kogo' ),
		'kogo_work'        => __( 'Products', 'kogo' ),
	);
}

/**
 * Apply the search-page filters to the main WordPress query.
 *
 * @param WP_Query $query Main query.
 * @return void
 */
function kogo_filter_search_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$content_types = kogo_search_content_types();
	$content_type  = isset( $_GET['content_type'] ) ? sanitize_key( wp_unslash( $_GET['content_type'] ) ) : '';
	$tag           = isset( $_GET['search_tag'] ) ? sanitize_title( wp_unslash( $_GET['search_tag'] ) ) : '';
	$artist_id     = isset( $_GET['search_artist'] ) ? absint( $_GET['search_artist'] ) : 0;

	$query->set( 'post_type', isset( $content_types[ $content_type ] ) ? $content_type : array_keys( $content_types ) );
	$query->set( 'posts_per_page', 10 );

	if ( $tag ) {
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'slug',
					'terms'    => $tag,
				),
			)
		);
	}

	if ( $artist_id ) {
		$query->set(
			'meta_query',
			array(
				'relation' => 'OR',
				array(
					'key'     => '_kogo_itgallery_artist_post_id',
					'value'   => $artist_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_kogo_itgallery_artist_post_ids',
					'value'   => 'i:' . $artist_id . ';',
					'compare' => 'LIKE',
				),
			)
		);
	}
}
add_action( 'pre_get_posts', 'kogo_filter_search_query' );

/**
 * Wrap the matching phrase in an escaped text string with mark elements.
 *
 * @param string $text Text to display.
 * @param string $term Search phrase.
 * @return string
 */
function kogo_highlight_search_term( $text, $term ) {
	$term = trim( (string) $term );
	if ( '' === $term ) {
		return esc_html( $text );
	}

	$pattern = '/(' . preg_quote( $term, '/' ) . ')/iu';
	$parts   = preg_split( $pattern, (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	$html    = '';

	foreach ( $parts as $part ) {
		$html .= preg_match( $pattern, $part ) ? '<mark>' . esc_html( $part ) . '</mark>' : esc_html( $part );
	}

	return $html;
}

/**
 * Render the full search results interface.
 *
 * @return string
 */
function kogo_search_results_shortcode() {
	global $wp_query;

	$term          = get_search_query( false );
	$content_types = kogo_search_content_types();
	$content_type  = isset( $_GET['content_type'] ) ? sanitize_key( wp_unslash( $_GET['content_type'] ) ) : '';
	$tag           = isset( $_GET['search_tag'] ) ? sanitize_title( wp_unslash( $_GET['search_tag'] ) ) : '';
	$artist_id     = isset( $_GET['search_artist'] ) ? absint( $_GET['search_artist'] ) : 0;
	$tags          = get_tags( array( 'hide_empty' => true ) );
	$artists       = get_posts(
		array(
			'post_type'      => 'kogo_artist',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	ob_start();
	?>
	<section class="kogo-search-results__filters" aria-label="Search filters">
		<form class="kogo-search-filters" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="hidden" name="s" value="<?php echo esc_attr( $term ); ?>">
			<label>
				<span class="kogo-search-results__sr-only"><?php esc_html_e( 'Content type', 'kogo' ); ?></span>
				<select name="content_type">
					<option value=""><?php esc_html_e( 'All content', 'kogo' ); ?></option>
					<?php foreach ( $content_types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $content_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span class="kogo-search-results__sr-only"><?php esc_html_e( 'Tag', 'kogo' ); ?></span>
				<select name="search_tag">
					<option value=""><?php esc_html_e( 'All tags', 'kogo' ); ?></option>
					<?php foreach ( $tags as $tag_option ) : ?>
						<option value="<?php echo esc_attr( $tag_option->slug ); ?>"<?php selected( $tag, $tag_option->slug ); ?>><?php echo esc_html( $tag_option->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span class="kogo-search-results__sr-only"><?php esc_html_e( 'Artist', 'kogo' ); ?></span>
				<select name="search_artist">
					<option value=""><?php esc_html_e( 'All artists', 'kogo' ); ?></option>
					<?php foreach ( $artists as $artist ) : ?>
						<option value="<?php echo esc_attr( $artist->ID ); ?>"<?php selected( $artist_id, $artist->ID ); ?>><?php echo esc_html( get_the_title( $artist ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit"><?php esc_html_e( 'Filter', 'kogo' ); ?></button>
		</form>
	</section>

	<section class="kogo-search-results__content" aria-labelledby="kogo-search-results-title">
		<h1 class="kogo-search-results__title" id="kogo-search-results-title">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: search phrase. */
					__( 'Search results for “%s”', 'kogo' ),
					$term
				)
			);
			?>
		</h1>

		<?php if ( $wp_query->have_posts() ) : ?>
			<ul class="kogo-search-results__list">
				<?php foreach ( $wp_query->posts as $result ) : ?>
					<?php
					$type        = get_post_type( $result );
					$type_object = get_post_type_object( $type );
					$label       = $content_types[ $type ] ?? ( $type_object ? $type_object->labels->singular_name : __( 'Content', 'kogo' ) );
					$excerpt = get_the_excerpt( $result );
					if ( ! $excerpt ) {
						$excerpt = get_post_field( 'post_content', $result );
					}
					$excerpt = wp_trim_words( wp_strip_all_tags( $excerpt ), 30, '…' );
					?>
					<li class="kogo-search-result">
						<p class="kogo-search-result__type"><?php echo esc_html( $label ); ?></p>
						<h2 class="kogo-search-result__title"><a href="<?php echo esc_url( get_permalink( $result ) ); ?>"><?php echo kogo_highlight_search_term( get_the_title( $result ), $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></h2>
						<?php if ( $excerpt ) : ?>
							<p class="kogo-search-result__excerpt"><?php echo kogo_highlight_search_term( $excerpt, $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="kogo-search-results__empty"><?php esc_html_e( 'No results found. Try another search phrase or remove a filter.', 'kogo' ); ?></p>
		<?php endif; ?>

		<?php
		$pagination = paginate_links(
			array(
				'current'   => max( 1, get_query_var( 'paged' ) ),
				'total'     => max( 1, $wp_query->max_num_pages ),
				'type'      => 'array',
				'prev_text' => '<span aria-hidden="true">←</span><span class="kogo-search-results__sr-only">' . esc_html__( 'Previous page', 'kogo' ) . '</span>',
				'next_text' => '<span aria-hidden="true">→</span><span class="kogo-search-results__sr-only">' . esc_html__( 'Next page', 'kogo' ) . '</span>',
			)
		);
		?>
		<?php if ( $pagination ) : ?>
			<nav class="kogo-search-pagination" aria-label="<?php esc_attr_e( 'Search results pages', 'kogo' ); ?>">
				<?php foreach ( $pagination as $page_link ) : ?>
					<?php echo wp_kses_post( $page_link ); ?>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</section>
	<?php

	return ob_get_clean();
}
add_shortcode( 'kogo_search_results', 'kogo_search_results_shortcode' );
