<?php
/** Searchable editorial post links and the exhibition Events slider. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const KOGO_EXHIBITION_POSTS_META = 'kogo_linked_post_ids';

/** Keep only unique Posts IDs; drafts can be linked ahead of publication. */
function kogo_sanitize_exhibition_post_ids( $ids ) {
	return array_values( array_filter( array_unique( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ), static function ( $id ) {
		return 'post' === get_post_type( $id ) && ! in_array( get_post_status( $id ), array( 'trash', 'auto-draft' ), true );
	} ) );
}

add_action( 'init', static function () {
	register_post_meta( 'kogo_exposition', KOGO_EXHIBITION_POSTS_META, array(
		'type'              => 'array',
		'single'            => true,
		'default'           => array(),
		'sanitize_callback' => 'kogo_sanitize_exhibition_post_ids',
		'auth_callback'     => static function ( $allowed, $key, $post_id ) { return current_user_can( 'edit_post', $post_id ); },
		'show_in_rest'      => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ) ),
	) );
} );

add_action( 'enqueue_block_editor_assets', static function () {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base || 'kogo_exposition' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script( 'kogo-exhibition-posts-editor', get_theme_file_uri( 'assets/exhibition-posts-editor.js' ), array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-data', 'wp-core-data', 'wp-compose', 'wp-i18n' ), wp_get_theme()->get( 'Version' ), true );
} );

function kogo_exhibition_events_shortcode() {
	$exhibition_id = get_the_ID();
	$ids = kogo_sanitize_exhibition_post_ids( get_post_meta( $exhibition_id, KOGO_EXHIBITION_POSTS_META, true ) );
	if ( 'kogo_exposition' !== get_post_type( $exhibition_id ) || ! $ids ) {
		return '';
	}
	$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'post__in' => $ids, 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'has_password' => false ) );
	if ( ! $posts ) {
		return '';
	}
	$archive_url = get_post_type_archive_link( 'post' );
	ob_start();
	?>
	<section id="events" class="kogo-posts-slider kogo-exhibition-events swiper" data-slider-label="<?php esc_attr_e( 'Exhibition events', 'kogo' ); ?>" data-slider-item-label="<?php esc_attr_e( 'posts', 'kogo' ); ?>">
		<div class="kogo-posts-slider__header">
			<h2 class="kogo-posts-slider__heading"><?php esc_html_e( 'Events', 'kogo' ); ?></h2>
			<div class="kogo-posts-slider__actions">
				<?php if ( $archive_url ) : ?>
					<div class="wp-block-buttons kogo-posts-slider__more"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'More', 'kogo' ); ?></a></div></div>
				<?php endif; ?>
			</div>
		</div>
		<ul class="wp-block-post-template kogo-posts-slider__items swiper-wrapper">
			<?php foreach ( $posts as $linked_post ) : ?>
				<li>
					<article class="kogo-posts-slider__card">
						<?php if ( has_post_thumbnail( $linked_post ) ) : ?>
							<figure class="kogo-posts-slider__image"><a href="<?php echo esc_url( get_permalink( $linked_post ) ); ?>"><?php echo get_the_post_thumbnail( $linked_post, 'large', array( 'loading' => 'lazy' ) ); ?></a></figure>
						<?php endif; ?>
						<time class="kogo-posts-slider__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $linked_post ) ); ?>"><?php echo esc_html( get_the_date( 'd.m.Y', $linked_post ) ); ?></time>
						<h3 class="kogo-posts-slider__title"><a href="<?php echo esc_url( get_permalink( $linked_post ) ); ?>"><?php echo esc_html( get_the_title( $linked_post ) ); ?></a></h3>
					</article>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_events', 'kogo_exhibition_events_shortcode' );

// FSE expands these shortcodes before rendering blocks. Core's wpautop then
// inserts empty paragraphs into the slider header, creating extra flex items.
add_filter( 'render_block_core/shortcode', static function ( $content, $block ) {
	$html = $block['innerHTML'] ?? '';
	return false !== strpos( $html, 'class="kogo-posts-slider kogo-exhibition-' ) ? $html : $content;
}, 10, 2 );
