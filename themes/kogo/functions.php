<?php

require_once __DIR__ . '/inc/kogo-itgallery.php';
require_once __DIR__ . '/inc/exhibition-archive.php';
require_once __DIR__ . '/inc/exhibition-single.php';
require_once __DIR__ . '/inc/exhibition-links.php';
require_once __DIR__ . '/inc/artist-grid.php';
require_once __DIR__ . '/inc/artist-single.php';
require_once __DIR__ . '/inc/post-single.php';
require_once __DIR__ . '/inc/search.php';

/**
 * General Theme Settings.
 *
 * @since v1.0
 *
 * @return void
 */
function kogo_theme_support() {
	// Make theme available for translation: Translations can be filed in the /languages/ directory.
	load_theme_textdomain( 'kogo', __DIR__ . '/languages' );

	// Add support for Post thumbnails.
	add_theme_support( 'post-thumbnails' );
	// Add support for responsive embedded content.
	add_theme_support( 'responsive-embeds' );
	// Add support for Block Styles.
	add_theme_support( 'wp-block-styles' );

	// Add support for Editor Styles.
	add_theme_support( 'editor-styles' );
	// Enqueue Editor Styles.
	add_editor_style(
		array( 'build/main.css', 'style-editor.css' )
	);
}
add_action( 'after_setup_theme', 'kogo_theme_support' );

/**
 * Enqueue editor stylesheet (for iframed Post Editor):
 * https://make.wordpress.org/core/2023/07/18/miscellaneous-editor-changes-in-wordpress-6-3/#post-editor-iframed
 *
 * @since v1.2.2
 *
 * @return void
 */
function kogo_load_editor_styles() {
	if ( is_admin() ) {
		$theme_version = wp_get_theme()->get( 'Version' );

		wp_enqueue_style( 'kogo-editor-main', get_theme_file_uri( 'build/main.css' ), array(), $theme_version );
		wp_enqueue_style( 'editor-style', get_theme_file_uri( 'style-editor.css' ), array( 'kogo-editor-main' ), $theme_version );
	}
}
add_action( 'enqueue_block_assets', 'kogo_load_editor_styles' );

// Disable Block Directory: https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/filters/editor-filters.md#block-directory
remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
remove_action( 'enqueue_block_editor_assets', 'gutenberg_enqueue_block_editor_assets_block_directory' );

/**
 * Custom Template part.
 *
 * @param array $areas Template part areas.
 *
 * @return array
 */
function kogo_custom_template_part_area( $areas ) {
	array_push(
		$areas,
		array(
			'area'        => 'query',
			'label'       => esc_html__( 'Query', 'kogo' ),
			'description' => esc_html__( 'Custom query area', 'kogo' ),
			'icon'        => 'layout',
			'area_tag'    => 'div',
		)
	);

	return $areas;
}
add_filter( 'default_wp_template_part_areas', 'kogo_custom_template_part_area' );

/**
 * Register theme pattern categories.
 *
 * @return void
 */
function kogo_register_pattern_categories() {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'kogo',
			array(
				'label' => esc_html__( 'Kogo Gallery', 'kogo' ),
			)
		);
	}
}
add_action( 'init', 'kogo_register_pattern_categories' );

/**
 * Add the newsletter callout as the first slide in the News query.
 *
 * @param string $block_content Rendered Post Template markup.
 * @param array  $block         Parsed Post Template block.
 *
 * @return string
 */
function kogo_prepend_newsletter_slide( $block_content, $block ) {
	$class_name = $block['attrs']['className'] ?? '';

	if ( false === strpos( $class_name, 'kogo-posts-slider__items' ) || false !== strpos( $class_name, 'kogo-posts-slider__items--related' ) ) {
		return $block_content;
	}

	$newsletter_slide = sprintf(
		'<li class="kogo-posts-slider__newsletter-slide swiper-slide"><a class="kogo-posts-slider__newsletter" href="#kogo-footer-newsletter" aria-label="%1$s"><span>%2$s</span><strong>%3$s</strong></a></li>',
		esc_attr__( 'Join our newsletter', 'kogo' ),
		esc_html__( 'Join our', 'kogo' ),
		esc_html__( 'newsletter!', 'kogo' )
	);

	return preg_replace( '/(<ul\b[^>]*>)/', '$1' . $newsletter_slide, $block_content, 1 );
}
add_filter( 'render_block_core/post-template', 'kogo_prepend_newsletter_slide', 10, 2 );

/**
 * Enqueue CSS Stylesheets and Javascript files.
 *
 * @return void
 */
function kogo_load_scripts() {
	$theme_version = wp_get_theme()->get( 'Version' );

	// 1. Styles.
	wp_enqueue_style( 'style', get_stylesheet_uri(), array(), $theme_version );
	wp_enqueue_style( 'main', get_theme_file_uri( 'build/main.css' ), array(), $theme_version, 'all' ); // main.scss: Compiled custom styles.

	if ( is_rtl() ) {
		wp_enqueue_style( 'rtl', get_theme_file_uri( 'build/rtl.css' ), array(), $theme_version, 'all' );
	}

	// 2. Scripts.
	wp_enqueue_script( 'mainjs', get_theme_file_uri( 'build/main.js' ), array(), $theme_version, true );
}
add_action( 'wp_enqueue_scripts', 'kogo_load_scripts' );
