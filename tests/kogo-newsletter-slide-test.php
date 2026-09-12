<?php
// Run with WordPress loaded: wp eval-file tests/kogo-newsletter-slide-test.php

$slider = array( 'attrs' => array( 'className' => 'kogo-posts-slider__items swiper-wrapper' ) );
$posts  = '<li id="post-1"><ul><li>Nested content</li></ul></li><li id="post-2">Two</li><li id="post-3">Three</li><li id="post-4">Four</li>';
$html   = '<ul class="wp-block-post-template">' . $posts . '</ul>';
$result = apply_filters( 'render_block_core/post-template', $html, $slider );
$document = new DOMDocument();
$document->loadHTML( $result );
$slides = ( new DOMXPath( $document ) )->query( '//ul[@class="wp-block-post-template"]/li' );
$order = array();
foreach ( $slides as $slide ) {
	$order[] = $slide->getAttribute( 'id' ) ?: 'newsletter';
}
if ( array( 'post-1', 'post-2', 'post-3', 'newsletter', 'post-4' ) !== $order ) {
	throw new RuntimeException( 'The newsletter must follow three complete posts, ignoring nested list items.' );
}

$short = apply_filters( 'render_block_core/post-template', '<ul><li>One</li><li>Two</li></ul>', $slider );
if ( false === strpos( $short, '<li>Two</li><li class="kogo-posts-slider__newsletter-slide' ) ) {
	throw new RuntimeException( 'The newsletter must follow the available posts in a shorter slider.' );
}
foreach ( array( 'other-items', 'kogo-posts-slider__items--related' ) as $class_name ) {
	if ( $html !== apply_filters( 'render_block_core/post-template', $html, array( 'attrs' => array( 'className' => $class_name ) ) ) ) {
		throw new RuntimeException( 'Unrelated post templates must remain unchanged.' );
	}
}

echo "Kogo newsletter slide test passed.\n";
