<?php
/** WooCommerce integration for the header and Editions catalogue. */

// The inherited WooCommerce query determines both the grid and its pagination.
add_filter( 'loop_shop_per_page', static function () { return 12; } );

/** Keep the header link pointed at the configured WooCommerce cart page. */
function kogo_header_cart_url( $content ) {
	if ( ! function_exists( 'wc_get_cart_url' ) || false === strpos( $content, 'kogo-header__icon-button--cart' ) ) {
		return $content;
	}
	$html = new WP_HTML_Tag_Processor( $content );
	while ( $html->next_tag( array( 'tag_name' => 'A', 'class_name' => 'kogo-header__icon-button--cart' ) ) ) {
		$html->set_attribute( 'href', wc_get_cart_url() );
	}
	return $html->get_updated_html();
}
add_filter( 'render_block_core/html', 'kogo_header_cart_url' );

/** Keep Editions active throughout the catalogue and WooCommerce purchase pages. */
add_filter( 'render_block_core/navigation-link', static function ( $content ) {
	if ( ! function_exists( 'is_woocommerce' ) || ! ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) { return $content; }
	$html = new WP_HTML_Tag_Processor( $content );
	while ( $html->next_tag( 'A' ) ) {
		if ( wp_parse_url( wc_get_page_permalink( 'shop' ), PHP_URL_PATH ) === wp_parse_url( $html->get_attribute( 'href' ), PHP_URL_PATH ) ) {
			$html->set_attribute( 'aria-current', is_shop() ? 'page' : 'location' );
		}
	}
	return $html->get_updated_html();
} );

/** Use category descriptions on category archives and page content on the shop. */
function kogo_editions_intro() {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return '';
	}
	if ( is_product_category() ) {
		$term_id = get_queried_object_id();
		$content = wp_kses_post( term_description( $term_id, 'product_cat' ) );
		$artist_id = (int) get_term_meta( $term_id, 'kogo_artist_post_id', true );
		if ( $artist_id && 'kogo_artist' === get_post_type( $artist_id ) && 'publish' === get_post_status( $artist_id ) ) {
			$content .= '<a class="wp-element-button kogo-editions__artist-link" href="' . esc_url( get_permalink( $artist_id ) ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'More about the artist (opens in a new tab)', 'kogo' ) . '">' . esc_html__( 'More about the artist', 'kogo' ) . '</a>';
		}
		return $content;
	}
	return apply_filters( 'the_content', get_post_field( 'post_content', wc_get_page_id( 'shop' ) ) );
}
add_shortcode( 'kogo_editions_intro', 'kogo_editions_intro' );

/** Optional artist relationship on the native product-category add/edit forms. */
function kogo_product_category_artist_field( $term ) {
	$editing = $term instanceof WP_Term;
	$artist_id = $editing ? (int) get_term_meta( $term->term_id, 'kogo_artist_post_id', true ) : 0;
	$artists = get_posts( array( 'post_type' => 'kogo_artist', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo $editing ? '<tr class="form-field"><th scope="row"><label for="kogo-category-artist">' : '<div class="form-field"><label for="kogo-category-artist">';
	esc_html_e( 'Artist', 'kogo' );
	echo $editing ? '</label></th><td>' : '</label>';
	wp_nonce_field( 'kogo_product_category_artist', 'kogo_product_category_artist_nonce' );
	echo '<select name="kogo_category_artist_id" id="kogo-category-artist" aria-describedby="kogo-category-artist-description"><option value="0">' . esc_html__( 'No artist', 'kogo' ) . '</option>';
	foreach ( $artists as $artist ) {
		echo '<option value="' . esc_attr( $artist->ID ) . '"' . selected( $artist_id, $artist->ID, false ) . '>' . esc_html( $artist->post_title ) . '</option>';
	}
	echo '</select><p class="description" id="kogo-category-artist-description">' . esc_html__( 'Optional. Adds a “More about the artist” button to this category page.', 'kogo' ) . '</p>';
	echo $editing ? '</td></tr>' : '</div>';
}
add_action( 'product_cat_add_form_fields', 'kogo_product_category_artist_field' );
add_action( 'product_cat_edit_form_fields', 'kogo_product_category_artist_field' );

function kogo_save_product_category_artist( $term_id ) {
	$nonce = $_POST['kogo_product_category_artist_nonce'] ?? '';
	if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'kogo_product_category_artist' ) || ! current_user_can( 'edit_term', $term_id ) || ! isset( $_POST['kogo_category_artist_id'] ) || ! is_scalar( $_POST['kogo_category_artist_id'] ) ) {
		return;
	}
	$artist_id = absint( wp_unslash( $_POST['kogo_category_artist_id'] ) );
	if ( ! $artist_id ) {
		delete_term_meta( $term_id, 'kogo_artist_post_id' );
	} elseif ( 'kogo_artist' === get_post_type( $artist_id ) && 'publish' === get_post_status( $artist_id ) ) {
		update_term_meta( $term_id, 'kogo_artist_post_id', $artist_id );
	}
}
add_action( 'created_product_cat', 'kogo_save_product_category_artist' );
add_action( 'edited_product_cat', 'kogo_save_product_category_artist' );

/** Add native disclosure controls without replacing WordPress category links. */
class Kogo_Editions_Category_Walker extends Walker_Category {
	public function start_el( &$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$item = '';
		parent::start_el( $item, $data_object, $depth, $args, $current_object_id );
		if ( ! $this->has_children || ! $item ) {
			$output .= $item;
			return;
		}
		$start = strpos( $item, '>' ) + 1;
		$open = in_array( (int) $data_object->term_id, $args['kogo_open_categories'], true );
		$output .= substr( $item, 0, $start ) . '<details class="kogo-editions__category-tree"' . ( $open ? ' open' : '' ) . '><summary>' . substr( $item, $start ) . '</summary>';
	}

	public function end_lvl( &$output, $depth = 0, $args = array() ) {
		parent::end_lvl( $output, $depth, $args );
		$output .= '</details>';
	}
}

/** Native category archives keep filtering, links, and pagination in WooCommerce. */
function kogo_editions_categories() {
	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		return '';
	}
	$current = is_product_category() ? get_queried_object() : null;
	$args = array(
		'taxonomy' => 'product_cat',
		'hide_empty' => false,
		'exclude' => get_option( 'default_product_cat' ),
		'orderby' => 'menu_order',
		'hierarchical' => true,
		'echo' => false,
	);
	$shop_url = wc_get_page_permalink( 'shop' );
	$open_categories = $current ? array_merge( array( (int) $current->term_id ), array_map( 'intval', get_ancestors( $current->term_id, 'product_cat', 'taxonomy' ) ) ) : array();
	$list = wp_list_categories( array_merge( $args, array( 'title_li' => '', 'use_desc_for_title' => false, 'current_category' => $current ? $current->term_id : 0, 'walker' => new Kogo_Editions_Category_Walker(), 'kogo_open_categories' => $open_categories ) ) );
	$dropdown = wp_dropdown_categories( array_merge( $args, array( 'name' => 'product_cat', 'id' => 'kogo-editions-category', 'value_field' => 'slug', 'show_option_all' => __( 'All editions', 'kogo' ), 'selected' => $current ? $current->slug : '' ) ) );
	$options = new WP_HTML_Tag_Processor( $dropdown );
	while ( $options->next_tag( 'OPTION' ) ) {
		if ( '0' === $options->get_attribute( 'value' ) ) {
			$options->set_attribute( 'value', '' );
		}
	}
	$dropdown = $options->get_updated_html();
	return '<nav class="kogo-editions__category-links" aria-label="' . esc_attr__( 'Edition categories', 'kogo' ) . '"><a class="kogo-editions__all" href="' . esc_url( $shop_url ) . '"' . ( is_shop() ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'All editions', 'kogo' ) . '</a><ul>' . $list . '</ul></nav>'
		. '<form class="kogo-editions__category-select" method="get" action="' . esc_url( $shop_url ) . '"><label for="kogo-editions-category">' . esc_html__( 'Category', 'kogo' ) . '</label><div>' . $dropdown . '<button type="submit">' . esc_html__( 'Filter', 'kogo' ) . '</button></div></form>';
}
add_shortcode( 'kogo_editions_categories', 'kogo_editions_categories' );

/** Template shortcode blocks render outside the post-content shortcode filter. */
function kogo_editions_shortcode_block( $content ) {
	return false !== strpos( $content, '[kogo_editions_' ) ? do_shortcode( shortcode_unautop( $content ) ) : $content;
}
add_filter( 'render_block_core/shortcode', 'kogo_editions_shortcode_block' );

function kogo_editions_product_badges( $content, $block, $instance ) {
	if ( ! function_exists( 'wc_get_product' ) || false === strpos( $block['attrs']['className'] ?? '', 'kogo-edition-card__image' ) ) {
		return $content;
	}
	$product = wc_get_product( $instance->context['postId'] ?? 0 );
	if ( ! $product ) {
		return $content;
	}
	$labels = array();
	if ( has_term( 'popular', 'product_tag', $product->get_id() ) ) {
		$labels['popular'] = __( 'Popular', 'kogo' );
	}
	if ( has_term( 'new', 'product_tag', $product->get_id() ) ) {
		$labels['new'] = __( 'New', 'kogo' );
	}
	if ( $product->is_on_sale() ) {
		$labels['sale'] = __( 'Sale', 'kogo' );
	}
	if ( ! $labels ) {
		return $content;
	}
	$badges = '<div class="kogo-edition-card__badges">';
	foreach ( $labels as $slug => $label ) {
		$badges .= '<span class="kogo-edition-card__badge kogo-edition-card__badge--' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</span>';
	}
	$badges .= '</div>';
	return preg_replace_callback( '/<div\b[^>]*>/', static function ( $match ) use ( $badges ) {
		return $match[0] . $badges;
	}, $content, 1 );
}
add_filter( 'render_block_woocommerce/product-image', 'kogo_editions_product_badges', 10, 3 );
