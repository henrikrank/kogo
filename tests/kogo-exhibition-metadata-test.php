<?php

define( 'ABSPATH', __DIR__ . '/' );

$pages = array(
	201 => (object) array( 'ID' => 201, 'post_title' => 'Sabine Vernere', 'post_type' => 'kogo_artist', 'post_status' => 'publish' ),
	202 => (object) array( 'ID' => 202, 'post_title' => 'Manual artist', 'post_type' => 'kogo_artist', 'post_status' => 'publish' ),
	203 => (object) array( 'ID' => 203, 'post_title' => 'Draft artist', 'post_type' => 'kogo_artist', 'post_status' => 'draft' ),
	204 => (object) array( 'ID' => 204, 'post_title' => 'Not an artist', 'post_type' => 'kogo_work', 'post_status' => 'publish' ),
);
$meta = array(
	101 => array( '_kogo_itgallery_payload' => array( 'artists' => array(
		array( 'id' => 4100, 'complete_name' => 'Sabīne Vernere' ),
		array( 'id' => 4102, 'name' => 'Draft artist' ),
		'Manual artist', 'Guest & collaborator', '   ',
	) ) ),
	102 => array( '_kogo_itgallery_artist_post_ids' => array( 201, 203, 204 ) ),
	104 => array( 'kogo_exhibition_curator' => '<b>Editor override</b>' ),
	201 => array( '_kogo_itgallery_id' => '4100' ),
	203 => array( '_kogo_itgallery_id' => '4102' ),
);
$copy = array(
	101 => "Last year, a section was curated by Francesca Gavin.\n\nTEAM\n\nCoordination and curation: Liina Raus, Šelda Puķīte",
	102 => 'The participating artists are listed here, the show is curated by Stella Mõttus.',
	103 => 'Last year, Kogo showcased works in a section curated by Francesca Gavin.',
	104 => 'Curator: Imported curator',
);

function add_filter() {}
function add_shortcode() {}
function get_post_meta( $id, $key ) { global $meta; return $meta[ $id ][ $key ] ?? ''; }
function get_posts( $args ) {
	global $pages;
	return array_filter( $pages, function ( $page ) use ( $args ) { return $page->post_type === $args['post_type'] && $page->post_status === $args['post_status']; } );
}
function get_the_title( $id ) { global $pages; return $pages[ $id ]->post_title ?? ''; }
function get_post_status( $id ) { global $pages; return $pages[ $id ]->post_status ?? ''; }
function get_post_type( $id ) { global $pages; return $pages[ $id ]->post_type ?? ''; }
function get_permalink( $id ) { return 'https://example.test/artists/' . $id . '/'; }
function get_post_field( $field, $id ) { global $copy; return 'post_content' === $field ? ( $copy[ $id ] ?? '' ) : ''; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return esc_html( $value ); }

require dirname( __DIR__ ) . '/themes/kogo/inc/exhibition-archive.php';
require dirname( __DIR__ ) . '/themes/kogo/inc/exhibition-single.php';

$linked = kogo_render_exhibition_artists( 101, true );
if ( false === strpos( $linked, '<a href="https://example.test/artists/201/">Sabīne Vernere</a>' ) ||
	false === strpos( $linked, '<a href="https://example.test/artists/202/">Manual artist</a>' ) ||
	false !== strpos( $linked, '/203/' ) || false === strpos( $linked, 'Guest &amp; collaborator' ) ||
	2 !== substr_count( $linked, '<a ' ) ) {
	fwrite( STDERR, "Artist links must match published pages by ID or exact name, preserving unmatched names as escaped text.\n" );
	exit( 1 );
}
if ( false !== strpos( kogo_render_exhibition_artists( 101 ), '<a ' ) ||
	1 !== substr_count( kogo_render_exhibition_artists( 102, true ), '<a ' ) ) {
	fwrite( STDERR, "Archive cards must stay plain; relationship fallback must reject drafts and non-artists.\n" );
	exit( 1 );
}
$hero = '<section id="intro">' . $linked . '</section>';
if ( $hero !== kogo_render_exhibition_shortcode_block( $hero, array( 'innerHTML' => '[kogo_exhibition_hero]' ), (object) array( 'context' => array( 'postId' => 101 ) ) ) ) {
	fwrite( STDERR, "Artist links in the hero must not trigger the archive-card shortcode replacement.\n" );
	exit( 1 );
}
foreach ( array( 101 => 'Liina Raus, Šelda Puķīte', 102 => 'Stella Mõttus', 103 => '', 104 => 'Editor override' ) as $id => $expected ) {
	if ( $expected !== kogo_get_exhibition_curator( $id ) ) {
		fwrite( STDERR, "Curator must use explicit credits or editorial override, not generic mentions of past shows.\n" );
		exit( 1 );
	}
}
echo "Kogo exhibition metadata test passed.\n";
