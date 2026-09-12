<?php
// Read-only LocalWP check: wp eval-file /absolute/path/to/tests/kogo-about-page-test.php
$page = get_page_by_path( 'about', OBJECT, 'page' );
if ( ! $page || 'publish' !== $page->post_status || 'page-notitle' !== get_post_meta( $page->ID, '_wp_page_template', true ) ) {
	throw new RuntimeException( 'About must remain a published page with the shared no-title template.' );
}
$content = $page->post_content;
foreach ( array( 'kogo-gallery', 'gallery', 'team', 'programme-curation', 'supporters', 'partners' ) as $id ) {
	if ( 1 !== substr_count( $content, 'id="' . $id . '"' ) || 1 !== substr_count( $content, 'href="#' . $id . '"' ) ) {
		throw new RuntimeException( 'About section links must have one matching target: ' . $id );
	}
}
if ( 4 !== substr_count( $content, '"className":"kogo-emphasized-text__columns"' ) || ! str_contains( $content, 'kogo-section-nav__scroller' ) || ! str_contains( $content, 'kogo-gallery-lightbox' ) ) {
	throw new RuntimeException( 'About must reuse the shared text grid, artist section menu and gallery viewer.' );
}
preg_match( '~<script class="kogo-gallery-data" type="application/json">(.*?)</script>~s', $content, $match );
$images = json_decode( $match[1] ?? '', true );
if ( ! is_array( $images ) || count( $images ) < 24 ) {
	throw new RuntimeException( 'About requires an exhibition gallery with at least 24 images.' );
}
foreach ( $images as $image ) {
	if ( ! wp_http_validate_url( $image['full'] ?? '' ) || ! wp_http_validate_url( $image['src'] ?? '' ) ) {
		throw new RuntimeException( 'Every gallery record requires valid full-size and thumbnail URLs.' );
	}
}
if ( str_contains( strtolower( $content ), 'lorem ipsum' ) || ! str_contains( $content, '"lightbox":{"enabled":true}' ) ) {
	throw new RuntimeException( 'About requires real copy and a lightbox-enabled introduction image.' );
}
echo "Kogo About page integration check passed.\n";
