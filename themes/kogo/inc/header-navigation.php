<?php
/** Keep click-to-expand submenus interactive inside the native menu overlay. */
function kogo_header_submenu_state( $content, $block ) {
	if ( ! in_array( 'kogo-header__navigation', explode( ' ', $block['attrs']['className'] ?? '' ), true ) ) {
		return $content;
	}
	// Older WordPress versions force every submenu open with the overlay. Use the
	// submenu's own native state instead; newer versions already use this binding.
	return str_replace( 'data-wp-bind--aria-expanded="state.isSubmenuOpen"', 'data-wp-bind--aria-expanded="state.isMenuOpen"', $content );
}
add_filter( 'render_block_core/navigation', 'kogo_header_submenu_state', 10, 2 );
