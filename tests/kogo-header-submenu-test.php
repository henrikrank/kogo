<?php
/** Run with: php tests/kogo-header-submenu-test.php */
function add_filter() {}
require __DIR__ . '/../themes/kogo/inc/header-navigation.php';
$markup = '<button data-wp-bind--aria-expanded="state.isSubmenuOpen" aria-label="Info submenu">Info</button>';
$header = array( 'attrs' => array( 'className' => 'extra kogo-header__navigation' ) );
$result = kogo_header_submenu_state( $markup, $header );
if ( false === strpos( $result, 'data-wp-bind--aria-expanded="state.isMenuOpen"' ) || false === strpos( $result, 'aria-label="Info submenu"' ) ) {
	throw new RuntimeException( 'Use the submenu state while preserving native actions and labels.' );
}
if ( $markup !== kogo_header_submenu_state( $markup, array() ) || $markup !== kogo_header_submenu_state( $markup, array( 'attrs' => array( 'className' => 'kogo-header__navigation-other' ) ) ) ) {
	throw new RuntimeException( 'Do not change other navigation blocks.' );
}
if ( $result !== kogo_header_submenu_state( $result, $header ) ) {
	throw new RuntimeException( 'Leave newer WordPress bindings unchanged.' );
}
echo "Kogo header submenu state test passed.\n";
