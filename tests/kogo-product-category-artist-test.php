<?php
// Run with WordPress loaded: wp --user=admin eval-file tests/kogo-product-category-artist-test.php

$artist = get_page_by_path( 'anna-mari-liivrand', OBJECT, 'kogo_artist' );
if ( ! $artist || ! current_user_can( 'manage_product_terms' ) ) {
	throw new RuntimeException( 'Run this check as a category editor with the sample artist available.' );
}
$result = wp_insert_term( 'Category artist test', 'product_cat', array( 'slug' => 'kogo-category-test-' . strtolower( wp_generate_password( 8, false ) ), 'description' => 'A description for this category.' ) );
if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
$term_id = $result['term_id'];
$before_post = $_POST;
$before_query = $GLOBALS['wp_query'];
$before_user = get_current_user_id();
try {
	$nonce = wp_create_nonce( 'kogo_product_category_artist' );
	$_POST = array( 'kogo_product_category_artist_nonce' => $nonce, 'kogo_category_artist_id' => $artist->ID );
	wp_update_term( $term_id, 'product_cat', array( 'description' => 'A description for this category.' ) );
	if ( $artist->ID !== (int) get_term_meta( $term_id, 'kogo_artist_post_id', true ) ) { throw new RuntimeException( 'Editing a category must save its artist.' ); }
	$_POST['kogo_category_artist_id'] = wc_get_page_id( 'shop' );
	wp_update_term( $term_id, 'product_cat', array() );
	if ( $artist->ID !== (int) get_term_meta( $term_id, 'kogo_artist_post_id', true ) ) { throw new RuntimeException( 'Non-artist posts must not replace the selected artist.' ); }
	$_POST = array( 'kogo_category_artist_id' => 0 );
	wp_update_term( $term_id, 'product_cat', array() );
	if ( $artist->ID !== (int) get_term_meta( $term_id, 'kogo_artist_post_id', true ) ) { throw new RuntimeException( 'A missing nonce must leave the artist unchanged.' ); }
	wp_set_current_user( 0 );
	$_POST['kogo_product_category_artist_nonce'] = wp_create_nonce( 'kogo_product_category_artist' );
	wp_update_term( $term_id, 'product_cat', array() );
	if ( $artist->ID !== (int) get_term_meta( $term_id, 'kogo_artist_post_id', true ) ) { throw new RuntimeException( 'Users without category permissions must not change its artist.' ); }
	wp_set_current_user( $before_user );
	$term = get_term( $term_id );
	$GLOBALS['wp_query'] = new WP_Query( array( 'taxonomy' => 'product_cat', 'term' => $term->slug ) );
	$intro = do_blocks( '<!-- wp:shortcode -->[kogo_editions_intro]<!-- /wp:shortcode -->' );
	$link = new WP_HTML_Tag_Processor( $intro );
	if ( false === strpos( $intro, 'A description for this category.' ) || ! $link->next_tag( array( 'tag_name' => 'A', 'class_name' => 'kogo-editions__artist-link' ) ) || get_permalink( $artist ) !== $link->get_attribute( 'href' ) || '_blank' !== $link->get_attribute( 'target' ) ) {
		throw new RuntimeException( 'Category introductions must show their description and an artist link in a new tab.' );
	}
	$title = do_blocks( '<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"kogo-editions__title"} /-->' );
	if ( false === strpos( $title, 'Category artist test' ) ) { throw new RuntimeException( 'Category archives must use their category title.' ); }
	$_POST = array( 'kogo_product_category_artist_nonce' => $nonce, 'kogo_category_artist_id' => 0 );
	wp_update_term( $term_id, 'product_cat', array() );
	if ( metadata_exists( 'term', $term_id, 'kogo_artist_post_id' ) || false !== strpos( kogo_editions_intro(), 'kogo-editions__artist-link' ) ) {
		throw new RuntimeException( 'No artist must clear the relationship and remove its button.' );
	}
	echo "Product category artist check passed.\n";
} finally {
	$_POST = $before_post;
	$GLOBALS['wp_query'] = $before_query;
	wp_set_current_user( $before_user );
	wp_delete_term( $term_id, 'product_cat' );
}
