<?php
// Run with WordPress loaded: wp --user=admin eval-file tests/kogo-exhibition-posts-test.php
// Uses a temporary exhibition and draft; removes both in finally.
$published = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 2, 'has_password' => false ) );
if ( count( $published ) < 2 ) { throw new RuntimeException( 'Two published Posts are required.' ); }
$exhibition_id = 0;
$draft_id = 0;
$user_id = get_current_user_id();
$before_post = $GLOBALS['post'] ?? null;
$request_links = static function ( $id, $ids ) {
	$request = new WP_REST_Request( 'POST', '/wp/v2/kogo_exposition/' . $id );
	$request->set_param( 'meta', array( KOGO_EXHIBITION_POSTS_META => $ids ) );
	return rest_do_request( $request );
};
$load_page = static function ( $id ) {
	$response = wp_remote_get( get_permalink( $id ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { throw new RuntimeException( 'The exhibition page must load.' ); }
	$document = new DOMDocument();
	@$document->loadHTML( '<?xml encoding="utf-8" ?>' . wp_remote_retrieve_body( $response ) );
	return new DOMXPath( $document );
};
try {
	$exhibition_id = wp_insert_post( array( 'post_type' => 'kogo_exposition', 'post_status' => 'publish', 'post_title' => 'Temporary exhibition Posts check', 'post_content' => 'Temporary exhibition check.' ), true );
	$draft_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Temporary unpublished event check' ), true );
	if ( is_wp_error( $exhibition_id ) || is_wp_error( $draft_id ) ) { throw new RuntimeException( 'Could not create temporary fixtures.' ); }
	$works = get_posts( array( 'post_type' => 'kogo_work', 'posts_per_page' => 1, 'fields' => 'ids' ) );
	update_post_meta( $exhibition_id, '_kogo_itgallery_work_post_ids', $works );
	$ids = array( $published[1]->ID, $draft_id, $published[0]->ID );
	if ( $ids !== kogo_sanitize_exhibition_post_ids( array_merge( $ids, array( $published[1]->ID, $exhibition_id, 0, 999999999 ) ) ) ) {
		throw new RuntimeException( 'Links must contain unique, existing Posts only.' );
	}
	$response = $request_links( $exhibition_id, $ids );
	if ( 200 !== $response->get_status() || $ids !== $response->get_data()['meta'][ KOGO_EXHIBITION_POSTS_META ] ) { throw new RuntimeException( 'The editor REST save must preserve multiple Posts, including drafts.' ); }
	$xpath = $load_page( $exhibition_id );
	$events = $xpath->query( '//section[@id="events"]' );
	if ( 1 !== $events->length || 2 !== $xpath->query( '//section[@id="events"]//ul/li' )->length ) { throw new RuntimeException( 'Only linked published Posts should appear in Events.' ); }
	$links = $xpath->query( '//section[@id="events"]//h3/a' );
	if ( get_permalink( $published[0] ) !== $links->item(0)->getAttribute('href') || get_permalink( $published[1] ) !== $links->item(1)->getAttribute('href') ) { throw new RuntimeException( 'Events must be ordered newest first and link to their Posts.' ); }
	if ( ! $xpath->query( '//section[@id="events"]//time' )->length || ! $xpath->query( '//section[@id="events"]//div[@class="kogo-posts-slider__actions"]' )->length ) { throw new RuntimeException( 'Post dates and the shared slider actions must be rendered.' ); }
	if ( $xpath->query( '//section[@id="events"]//p' )->length || ( $works && $xpath->query( '//section[@id="works"]//p[not(normalize-space())]' )->length ) ) { throw new RuntimeException( 'Core shortcode formatting must not add empty paragraphs that misalign slider controls.' ); }
	if ( $works && 1 !== $xpath->query( '//section[@id="events"]/following-sibling::section[@id="works"]' )->length ) { throw new RuntimeException( 'Events must appear before Works.' ); }
	$GLOBALS['post'] = get_post( $exhibition_id );
	kogo_exhibition_events_shortcode();
	if ( $exhibition_id !== get_the_ID() ) { throw new RuntimeException( 'Rendering Events must preserve the exhibition context for later blocks.' ); }

	wp_set_current_user( 0 );
	if ( $request_links( $exhibition_id, array() )->get_status() < 400 || $ids !== get_post_meta( $exhibition_id, KOGO_EXHIBITION_POSTS_META, true ) ) { throw new RuntimeException( 'Visitors must not be allowed to change links.' ); }
	wp_set_current_user( $user_id );
	foreach ( array( array( $draft_id ), array() ) as $empty_frontend ) {
		if ( 200 !== $request_links( $exhibition_id, $empty_frontend )->get_status() || $load_page( $exhibition_id )->query( '//section[@id="events"]' )->length ) { throw new RuntimeException( 'Events must disappear when no linked Posts are publicly visible.' ); }
	}
	echo "Exhibition Posts REST and frontend integration check passed.\n";
} finally {
	wp_set_current_user( $user_id );
	$GLOBALS['post'] = $before_post;
	if ( is_int( $draft_id ) && $draft_id ) { wp_delete_post( $draft_id, true ); }
	if ( is_int( $exhibition_id ) && $exhibition_id ) { wp_delete_post( $exhibition_id, true ); }
}
