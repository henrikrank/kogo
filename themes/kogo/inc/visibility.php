<?php
/** Visitor visibility preferences, shared by every frontend page. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Restore preferences in the head, before content paints, including headerless pages.
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'kogo-visibility', get_theme_file_uri( 'assets/visibility.js' ), array(), filemtime( __DIR__ . '/../assets/visibility.js' ), false );
} );

function kogo_render_visibility( $content, $block ) {
	if ( is_admin() || 'header' !== ( $block['attrs']['slug'] ?? '' ) ) {
		return $content;
	}

	ob_start();
	?>
	<section class="kogo-visibility" id="kogo-visibility" aria-labelledby="kogo-visibility-title" hidden>
		<form class="kogo-visibility__form">
			<h2 class="kogo-visibility__title" id="kogo-visibility-title"><?php esc_html_e( 'Visibility settings', 'kogo' ); ?></h2>
			<fieldset>
				<legend><?php esc_html_e( 'Text size', 'kogo' ); ?></legend>
				<label class="kogo-visibility__option kogo-visibility__option--medium"><input type="radio" name="textSize" value="medium" checked><span><?php esc_html_e( 'Medium (default)', 'kogo' ); ?></span></label>
				<label class="kogo-visibility__option kogo-visibility__option--large"><input type="radio" name="textSize" value="large"><span><?php esc_html_e( 'Large', 'kogo' ); ?></span></label>
				<label class="kogo-visibility__option kogo-visibility__option--very-large"><input type="radio" name="textSize" value="very-large"><span><?php esc_html_e( 'Very large', 'kogo' ); ?></span></label>
			</fieldset>
			<fieldset>
				<legend><?php esc_html_e( 'Line spacing', 'kogo' ); ?></legend>
				<label class="kogo-visibility__option kogo-visibility__option--medium"><input type="radio" name="lineSpacing" value="2" checked><span><?php esc_html_e( '2× (default)', 'kogo' ); ?></span></label>
				<label class="kogo-visibility__option kogo-visibility__option--large"><input type="radio" name="lineSpacing" value="4"><span><?php esc_html_e( '4×', 'kogo' ); ?></span></label>
				<label class="kogo-visibility__option kogo-visibility__option--very-large"><input type="radio" name="lineSpacing" value="6"><span><?php esc_html_e( '6×', 'kogo' ); ?></span></label>
			</fieldset>
			<fieldset>
				<legend><?php esc_html_e( 'Contrast', 'kogo' ); ?></legend>
				<label class="kogo-visibility__option kogo-visibility__option--medium"><input type="radio" name="contrast" value="regular" checked><span><?php esc_html_e( 'Regular', 'kogo' ); ?></span></label>
				<label class="kogo-visibility__option kogo-visibility__option--contrast"><input type="radio" name="contrast" value="high"><span><?php esc_html_e( 'High-contrast (yellow text on black background)', 'kogo' ); ?></span></label>
			</fieldset>
			<div class="kogo-visibility__actions">
				<button class="kogo-visibility__apply" type="submit"><?php esc_html_e( 'Apply settings', 'kogo' ); ?></button>
				<button class="kogo-visibility__reset" type="reset"><?php esc_html_e( 'Reset', 'kogo' ); ?></button>
			</div>
			<p class="screen-reader-text" role="status" data-visibility-status></p>
		</form>
		<button class="kogo-visibility__close" type="button" aria-label="<?php esc_attr_e( 'Close visibility settings', 'kogo' ); ?>"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 4 12 12M16 4 4 16" stroke="currentColor" stroke-width="1.5"/></svg></button>
	</section>
	<?php
	return ob_get_clean() . $content;
}
// Run after announcements so the panel precedes both announcement and sticky header.
add_filter( 'render_block_core/template-part', 'kogo_render_visibility', 30, 2 );
