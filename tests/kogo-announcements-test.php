<?php
/** Run with: php tests/kogo-announcements-test.php */

define( 'ABSPATH', __DIR__ . '/' );
$stored = array();
$allowed = true;
$nonce_checked = false;
function add_action() {}
function add_filter() {}
function __( $text ) { return $text; }
function get_option() { global $stored; return $stored; }
function update_option( $name, $data ) { global $stored; $stored = $data; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function current_user_can() { global $allowed; return $allowed; }
function check_admin_referer() { global $nonce_checked; $nonce_checked = true; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( $value ) ) ); }
function esc_url_raw( $url ) { return preg_match( '/^https?:\/\//', $url ) ? $url : ''; }
function wp_generate_uuid4() { static $id = 0; return 'announcement-' . ++$id; }
function admin_url( $path ) { return '/wp-admin/' . $path; }
function wp_safe_redirect() { throw new RuntimeException( 'redirect' ); }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_html__( $value ) { return esc_html( $value ); }
function esc_attr__( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function is_admin() { return false; }

require dirname( __DIR__ ) . '/themes/kogo/inc/announcements.php';

function check( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}
function submit( $fields ) {
	$_POST = $fields;
	try { kogo_save_announcement(); } catch ( RuntimeException $error ) { return $error->getMessage(); }
}

$header = array( 'attrs' => array( 'slug' => 'header' ) );
check( '<header>Site</header>' === kogo_render_announcement( '<header>Site</header>', $header ), 'An empty configuration must leave the header intact.' );
check( 'redirect' === submit( array( 'announcement_action' => 'save', 'message' => 'First message', 'active' => '1' ) ), 'Save a first active announcement.' );
$first = $stored['active_id'];
check( $nonce_checked, 'Every mutation must verify its nonce.' );
unset( $GLOBALS['kogo_announcement_rendered'] );
ob_start();
kogo_announcement_body_fallback();
$fallback = ob_get_clean();
check( strpos( $fallback, 'First message' ) !== false, 'Templates without a header must still show the announcement.' );
ob_start();
kogo_announcement_body_fallback();
check( '' === ob_get_clean(), 'The body fallback must not duplicate an announcement rendered with the header.' );
submit( array( 'announcement_action' => 'save', 'message' => 'Second message', 'link_url' => 'https://example.com', 'active' => '1' ) );
$second = $stored['active_id'];
check( $first !== $second && count( $stored['items'] ) === 2, 'Activating a new announcement must preserve the previous one and select only the new one.' );
submit( array( 'announcement_action' => 'save', 'announcement_id' => $first, 'message' => 'Updated draft' ) );
check( $second === $stored['active_id'], 'Saving an inactive announcement must preserve the current active one.' );
$rendered = kogo_render_announcement( '<header>Site</header>', $header );
check( strpos( $rendered, 'Second message' ) !== false && strpos( $rendered, 'Updated draft' ) === false && strpos( $rendered, '>More info</a>' ) !== false, 'Render only the active announcement with its optional link.' );
check( strpos( $rendered, 'sessionStorage' ) !== false && strpos( $rendered, 'data-announcement-key' ) !== false && str_ends_with( $rendered, '<header>Site</header>' ), 'Dismissal must use session storage and the announcement must precede the header.' );
check( strpos( $rendered, 'class="kogo-announcement__copy" aria-hidden="true"' ) !== false && strpos( $rendered, '<a tabindex="-1"' ) !== false, 'The marquee copy must not repeat the announcement for screen readers or keyboard navigation.' );
submit( array( 'announcement_action' => 'activate', 'announcement_id' => $first ) );
check( $first === $stored['active_id'], 'A saved announcement can be shown again.' );
$old_markup = kogo_render_announcement( '', $header );
submit( array( 'announcement_action' => 'save', 'announcement_id' => $first, 'message' => 'Text with <b>markup</b> & punctuation', 'active' => '1' ) );
check( strpos( kogo_render_announcement( '', $header ), 'Text with markup &amp; punctuation' ) !== false, 'Messages are plain text and safely escaped.' );
preg_match( '/data-announcement-key="([^"]+)"/', $old_markup, $old_key );
preg_match( '/data-announcement-key="([^"]+)"/', kogo_render_announcement( '', $header ), $new_key );
check( $old_key[1] !== $new_key[1], 'Editing a message must give the updated announcement a new dismissal key.' );
submit( array( 'announcement_action' => 'deactivate', 'announcement_id' => $first ) );
check( '' === $stored['active_id'] && count( $stored['items'] ) === 2, 'Hiding must retain saved announcements.' );
submit( array( 'announcement_action' => 'activate', 'announcement_id' => $second ) );
submit( array( 'announcement_action' => 'delete', 'announcement_id' => $second ) );
check( '' === $stored['active_id'] && ! isset( $stored['items'][ $second ] ), 'Deleting the active announcement must also clear its selection.' );
$before = $stored;
check( 'redirect' !== submit( array( 'announcement_action' => 'save', 'message' => '' ) ), 'Reject empty messages.' );
check( 'redirect' !== submit( array( 'announcement_action' => 'save', 'message' => 'Bad URL', 'link_url' => 'javascript:alert(1)' ) ), 'Reject unsafe link protocols.' );
check( 'redirect' !== submit( array( 'announcement_action' => 'activate', 'announcement_id' => 'missing' ) ), 'Reject missing IDs.' );
check( 'redirect' !== submit( array( 'announcement_action' => array( 'save' ) ) ), 'Reject malformed input arrays.' );
$allowed = false;
check( 'redirect' !== submit( array( 'announcement_action' => 'delete', 'announcement_id' => $first ) ) && $before === $stored, 'Unauthorized or invalid input must not change saved data.' );
echo "Kogo announcements test passed.\n";
