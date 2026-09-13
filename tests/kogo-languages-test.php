<?php
/** Run with: php tests/kogo-languages-test.php */
$languages = null;
function add_filter() {}
function apply_filters( $name, $value, $args ) {
	global $languages;
	if ( 'wpml_active_languages' !== $name || array( 'skip_missing' => 0 ) !== $args ) {
		throw new RuntimeException( 'Use WPML translation URLs with a homepage fallback.' );
	}
	return $languages;
}
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_attr__( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
require __DIR__ . '/../themes/kogo/inc/languages.php';
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

$header = '<div><a class="cart" href="/cart/">Cart</a><nav class="kogo-header__languages" aria-label="Language"><a href="#">EE</a><a href="#" aria-current="page">EN</a></nav></div>';
check( $header === kogo_header_languages( $header ), 'Without WPML, keep the original header.' );
$languages = array(
	array( 'language_code' => 'et', 'native_name' => 'Eesti', 'url' => 'https://example.com/et/meist/', 'active' => 0 ),
	array( 'language_code' => 'en', 'native_name' => 'English', 'url' => 'https://example.com/about/?a=1&b=2', 'active' => 1 ),
);
$html = kogo_header_languages( $header );
check( false === strpos( $html, 'href="#"' ), 'Replace the placeholder links.' );
check( false !== strpos( $html, 'href="https://example.com/et/meist/" lang="et" hreflang="et" aria-label="Eesti">EE</a>' ), 'Use the WPML translation URL and preserve EE.' );
check( false !== strpos( $html, 'href="https://example.com/about/?a=1&amp;b=2" lang="en" hreflang="en" aria-label="English" aria-current="page">EN</a>' ), 'Mark the current language and escape URLs.' );
check( false !== strpos( $html, '<a class="cart" href="/cart/">Cart</a>' ), 'Preserve the other header tools.' );
$languages[0]['active'] = 1;
$languages[1]['active'] = 0;
$languages[1]['url'] = 'https://example.com/';
$html = kogo_header_languages( $header );
check( false !== strpos( $html, 'aria-label="Eesti" aria-current="page">EE</a>' ) && 1 === substr_count( $html, 'aria-current="page"' ), 'Move the active state when the language changes.' );
check( false !== strpos( $html, 'href="https://example.com/"' ), 'Honor WPML homepage fallback URLs.' );
check( '<p>Other block</p>' === kogo_header_languages( '<p>Other block</p>' ), 'Ignore unrelated HTML blocks.' );
echo "Kogo WPML language selector test passed.\n";
