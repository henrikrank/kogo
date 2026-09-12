<?php
// Run with WordPress loaded: wp eval-file tests/kogo-editions-test.php

if ( 12 !== apply_filters( 'loop_shop_per_page', 16 ) ) {
	throw new RuntimeException( 'WooCommerce archives must paginate after twelve editions.' );
}

for ( $number = 1; $number <= 12; ++$number ) {
	$product = wc_get_product( wc_get_product_id_by_sku( sprintf( 'KOGO-SAMPLE-%03d', $number ) ) );
	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || ! wp_attachment_is_image( $product->get_image_id() ) ) {
		throw new RuntimeException( 'Sample product must have an image and be purchasable: ' . $number );
	}
	if ( '15.50' !== $product->get_regular_price() || ( 2 === $number ? '9.00' : '' ) !== $product->get_sale_price() ) {
		throw new RuntimeException( 'Product prices must match the supplied screenshot.' );
	}
	$block = new WP_Block( array( 'blockName' => 'woocommerce/product-image', 'attrs' => array( 'className' => 'kogo-edition-card__image', 'showSaleBadge' => false ) ), array( 'postId' => $product->get_id() ) );
	$html = $block->render();
	$expected = array( 'popular' => 1 === $number, 'new' => in_array( $number, array( 2, 3 ), true ), 'sale' => 2 === $number );
	foreach ( $expected as $slug => $visible ) {
		if ( $visible !== ( false !== strpos( $html, 'kogo-edition-card__badge--' . $slug ) ) ) {
			throw new RuntimeException( 'Product badge does not match its tags or sale status.' );
		}
	}
}
if ( 'Editions' !== get_the_title( wc_get_page_id( 'shop' ) ) || '/editions/' !== wp_parse_url( wc_get_page_permalink( 'shop' ), PHP_URL_PATH ) ) {
	throw new RuntimeException( 'The shop must be called Editions at /editions/.' );
}
if ( '<div>Untouched</div>' !== kogo_editions_product_badges( '<div>Untouched</div>', array( 'attrs' => array() ), (object) array( 'context' => array() ) ) ) {
	throw new RuntimeException( 'Other product-image blocks must remain unchanged.' );
}
$intro = render_block( parse_blocks( '<!-- wp:shortcode -->[kogo_editions_intro]<!-- /wp:shortcode -->' )[0] );
if ( false !== strpos( $intro, '[kogo_editions_intro]' ) || false === strpos( $intro, '/works/' ) ) {
	throw new RuntimeException( 'The archive must render the editable shop introduction.' );
}
$categories = render_block( parse_blocks( '<!-- wp:shortcode -->[kogo_editions_categories]<!-- /wp:shortcode -->' )[0] );
$html = new WP_HTML_Tag_Processor( $categories );
if ( ! $html->next_tag( 'NAV' ) || ! $html->next_tag( 'A' ) || wc_get_page_permalink( 'shop' ) !== $html->get_attribute( 'href' ) ) {
	throw new RuntimeException( 'The category sidebar must link back to all editions.' );
}
if ( ! $html->next_tag( 'OPTION' ) || '' !== $html->get_attribute( 'value' ) ) {
	throw new RuntimeException( 'Selecting All editions must clear the category filter.' );
}
if ( false !== strpos( $categories, '<p></p>' ) || false !== strpos( $categories, '[kogo_editions_categories]' ) ) {
	throw new RuntimeException( 'The sidebar must render without raw shortcodes or empty paragraphs.' );
}

// Exercise nested branches without adding sample categories to the database.
$parent = get_term_by( 'slug', 'works-of-kogo-artists', 'product_cat' );
$child = get_term_by( 'slug', 'anna-mari-liivrand', 'product_cat' );
$grandchild = clone $child;
$grandchild->term_id = 900001;
$grandchild->parent = $child->term_id;
$grandchild->name = 'Nested category';
$other = clone $parent;
$other->term_id = 900002;
$other_child = clone $child;
$other_child->term_id = 900003;
$other_child->parent = $other->term_id;
$terms = array( $parent, $child, $grandchild, $other, $other_child );
$args = array( 'style' => 'list', 'use_desc_for_title' => false, 'feed' => '', 'feed_image' => '', 'show_count' => false, 'current_category' => 0, 'kogo_open_categories' => array() );
foreach ( array( array(), array( (int) $parent->term_id, (int) $child->term_id ) ) as $open_categories ) {
	$args['kogo_open_categories'] = $open_categories;
	$tree = ( new Kogo_Editions_Category_Walker() )->walk( $terms, 0, $args );
	$document = new DOMDocument();
	@$document->loadHTML( '<ul>' . $tree . '</ul>' );
	$xpath = new DOMXPath( $document );
	if ( 3 !== $xpath->query( '//details' )->length || 3 !== $xpath->query( '//details/summary/a' )->length || 3 !== $xpath->query( '//details/ul' )->length || count( $open_categories ) !== $xpath->query( '//details[@open]' )->length || 1 !== $xpath->query( '//details/ul/li/details/ul/li/a' )->length ) {
		throw new RuntimeException( 'Only parents should have disclosures, with valid nesting and the active ancestor path open.' );
	}
}
// Each HTTP request gets WooCommerce's normal page detection and request caches.
$shop_path = wp_parse_url( wc_get_page_permalink( 'shop' ), PHP_URL_PATH );
$routes = array(
	'shop' => wc_get_page_permalink( 'shop' ),
	'product' => get_permalink( wc_get_product_id_by_sku( 'KOGO-SAMPLE-002' ) ),
	'category' => get_term_link( get_term_by( 'slug', 'top-picks', 'product_cat' ) ),
	'tag' => get_term_link( get_term_by( 'slug', 'new', 'product_tag' ) ),
	'cart' => wc_get_page_permalink( 'cart' ),
	'checkout' => wc_get_page_permalink( 'checkout' ),
	'account' => wc_get_page_permalink( 'myaccount' ),
	'artist' => get_permalink( get_page_by_path( 'anna-mari-liivrand', OBJECT, 'kogo_artist' ) ),
);
foreach ( $routes as $route => $url ) {
	$response = wp_remote_get( $url );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		throw new RuntimeException( 'The navigation check could not load the ' . $route . ' page.' );
	}
	$header = new WP_HTML_Tag_Processor( wp_remote_retrieve_body( $response ) );
	$found = false;
	while ( $header->next_tag( array( 'tag_name' => 'A', 'class_name' => 'wp-block-navigation-item__content' ) ) ) {
		if ( $shop_path !== wp_parse_url( $header->get_attribute( 'href' ), PHP_URL_PATH ) ) { continue; }
		$found = true;
		$expected = 'artist' === $route ? null : ( 'shop' === $route ? 'page' : 'location' );
		if ( $expected !== $header->get_attribute( 'aria-current' ) ) {
			throw new RuntimeException( 'Editions has an incorrect active state on the ' . $route . ' page.' );
		}
		break;
	}
	if ( ! $found ) { throw new RuntimeException( 'The header must include Editions on the ' . $route . ' page.' ); }
}
echo "Kogo Editions test passed.\n";
