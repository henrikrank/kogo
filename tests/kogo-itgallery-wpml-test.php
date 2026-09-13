<?php
/** wp --context=admin eval-file tests/kogo-itgallery-wpml-test.php (requires WPML). */
global $sitepress, $wpdb, $wp_filter;
if ( ! $sitepress ) { throw new RuntimeException( 'This integration check requires WPML.' ); }
function sync_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$property = new ReflectionProperty( Kogo_ITGallery::class, 'post_types' );
$original_types = $property->getValue();
$types = array( 'artist' => 'kogo_sync_artist_t', 'work' => 'kogo_sync_work_t', 'exposition' => 'kogo_sync_expo_t' );
$previous_language = $sitepress->get_current_language();
$previous_settings = $sitepress->get_settings();
$previous_sync = get_option( Kogo_ITGallery::LAST_SYNC );
$data = array();
foreach ( array( 'en_EN', 'et_EE' ) as $locale ) {
	$et = 'et_EE' === $locale;
	$data[ $locale ] = array(
		'artists' => array( array( 'id' => 700001, 'name' => 'Fixture', 'surname' => 'Artist', 'bio' => $et ? "Kunstniku tutvustus.\n\nPikem elulugu." : "Artist introduction.\n\nA longer biography." ) ),
		'works' => array( array( 'id' => 700002, 'name' => $et ? 'Teos' : 'Work', 'description' => $et ? 'Teose kirjeldus.' : 'Work description.', 'technique' => $et ? 'Õli lõuendil' : 'Oil on canvas', 'artist' => array( 'id' => 700001 ) ) ),
		'expositions' => array( array( 'id' => 700003, 'name' => $et ? 'Näitus' : 'Exhibition', 'description' => $et ? "Näituse idee.\n\nNäituse tekst.\n\nMEESKOND\n\nKuraator: Mari." : "Exhibition idea.\n\nExhibition text.\n\nTEAM\n\nCurator: Mary.", 'artists' => array( array( 'id' => 700001 ) ), 'works' => array( array( 'id' => 700002 ) ) ) ),
	);
}
$fail = false;
$http = static function ( $pre, $args, $url ) use ( &$data, &$fail ) {
	if ( ! str_starts_with( $url, Kogo_ITGallery::API_BASE_URL . '/' ) ) { return $pre; }
	parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
	$endpoint = basename( wp_parse_url( $url, PHP_URL_PATH ) );
	if ( $fail && 'et_EE' === $query['language'] ) { return new WP_Error( 'fixture_failure', 'Translation request failed.' ); }
	$items = array_slice( $data[ $query['language'] ][ $endpoint ], ( (int) $query['page'] - 1 ) * 100, 100 );
	return array( 'headers' => array(), 'body' => wp_json_encode( array( 'data' => $items ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
};
$ids = array();
try {
	wpml_load_core_tm();
	WPML_Config::load_config_run();
	$sitepress->set_setting( 'sync_delete', 1, true );
	$property->setValue( null, $types );
	foreach ( $types as $type ) {
		register_post_type( $type, array( 'public' => true, 'supports' => array( 'title', 'editor', 'excerpt' ) ) );
		wpml_load_settings_helper()->set_post_type_display_as_translated( $type );
	}
	$trash_hooks = array();
	foreach ( $wp_filter['wp_trash_post']->callbacks ?? array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];
			if ( is_array( $function ) && $function[0] instanceof WPML_Post_Translation && 'trashed_post_actions' === $function[1] ) { $trash_hooks[] = $function; }
		}
	}
	sync_check( count( $trash_hooks ) > 0, 'Run this check with --context=admin to exercise WPML translation trash hooks.' );
	$sitepress->switch_lang( 'en' );
	$legacy = wp_insert_post( array( 'post_type' => $types['artist'], 'post_status' => 'publish', 'post_title' => 'Locally edited artist', 'post_content' => 'Local biography before block migration.' ) );
	update_post_meta( $legacy, '_kogo_itgallery_id', '700001' );
	update_post_meta( $legacy, '_kogo_itgallery_hash', 'legacy-schema-hash' );
	update_post_meta( $legacy, '_kogo_itgallery_payload', $data['en_EN']['artists'][0] );
	$source = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $legacy, 'element_type' => 'post_' . $types['artist'] ) );
	$sitepress->switch_lang( 'et' );
	$manual_translation = wp_insert_post( array( 'post_type' => $types['artist'], 'post_status' => 'publish', 'post_title' => 'Minu kunstnik', 'post_content' => '<!-- wp:paragraph --><p>Minu enda tõlgitud elulugu.</p><!-- /wp:paragraph -->' ) );
	do_action( 'wpml_set_element_language_details', array( 'element_id' => $manual_translation, 'element_type' => 'post_' . $types['artist'], 'trid' => $source->trid, 'language_code' => 'et', 'source_language_code' => 'en' ) );
	$sitepress->switch_lang( $previous_language );
	add_filter( 'pre_http_request', $http, 10, 3 );
	$connector = new Kogo_ITGallery();
	$report = $connector->sync();
	sync_check( ! is_wp_error( $report ), is_wp_error( $report ) ? $report->get_error_message() : 'Sync failed.' );
	sync_check( 4 === array_sum( array_column( $report, 'created' ) ) && 2 === array_sum( array_column( $report, 'updated' ) ), 'Reuse existing legacy and manual WPML posts while creating missing translations.' );
	foreach ( $types as $kind => $type ) {
		foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1, 'suppress_filters' => true ) ) as $post ) {
			$language = get_post_meta( $post->ID, '_kogo_itgallery_language', true );
			$ids[ $language ][ $kind ] = $post->ID;
			sync_check( has_blocks( $post->post_content ), 'Save the imported text as editor blocks.' );
		}
		$en = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $ids['en'][ $kind ], 'element_type' => 'post_' . $type ) );
		$et = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $ids['et'][ $kind ], 'element_type' => 'post_' . $type ) );
		sync_check( $en->trid === $et->trid && 'et' === $et->language_code && 'en' === $et->source_language_code, 'Link translations through native WPML language details.' );
	}
	sync_check( $legacy === $ids['en']['artist'] && $manual_translation === $ids['et']['artist'], 'Reuse the manually linked translation even without an imported ID.' );
	sync_check( str_contains( get_post_field( 'post_content', $legacy ), 'Local biography before block migration.' ) && 'Locally edited artist' === get_post_field( 'post_title', $legacy ), 'Keep legacy local text and title during block migration.' );
	sync_check( str_contains( get_post_field( 'post_content', $manual_translation ), 'Minu enda tõlgitud elulugu.' ), 'Preserve an existing hand-written WPML translation.' );
	sync_check( false !== strpos( get_post_field( 'post_content', $ids['et']['exposition'] ), 'Näituse tekst.' ), 'Store the API Estonian copy.' );
	sync_check( 'Teos' === get_post_field( 'post_title', $ids['et']['work'] ), 'Import the translated work title.' );
	sync_check( $ids['et']['artist'] === (int) get_post_meta( $ids['et']['work'], '_kogo_itgallery_artist_post_id', true ), 'Point Estonian works at the Estonian artist.' );
	sync_check( array( $ids['et']['work'] ) === get_post_meta( $ids['et']['exposition'], '_kogo_itgallery_work_post_ids', true ), 'Use Estonian works in the Estonian exhibition.' );
	sync_check( 'et' !== $sitepress->get_current_language() || 'et' === $previous_language, 'Restore the previous language after syncing.' );
	$report = $connector->sync();
	sync_check( 6 === array_sum( array_column( $report, 'unchanged' ) ), 'A repeated sync must reuse all IDs and skip unchanged posts.' );
	$edited = str_replace( 'Näituse tekst.', 'Minu toimetatud tekst.', get_post_field( 'post_content', $ids['et']['exposition'] ) );
	wp_update_post( wp_slash( array( 'ID' => $ids['et']['exposition'], 'post_content' => $edited, 'post_title' => 'Toimetatud näitus' ) ) );
	$data['en_EN']['expositions'][0]['description'] .= "\n\nNew paragraph.";
	$data['et_EE']['expositions'][0]['description'] .= "\n\nUus lõik.";
	$data['et_EE']['works'][0]['technique'] = 'Akrüül lõuendil';
	$data['en_EN']['artists'][0]['bio'] = 'New remote biography after migration.';
	$data['et_EE']['artists'][0]['bio'] = 'Uus elulugu pärast plokkide loomist.';
	$report = $connector->sync();
	sync_check( $edited === get_post_field( 'post_content', $ids['et']['exposition'] ) && 'Toimetatud näitus' === get_post_field( 'post_title', $ids['et']['exposition'] ), 'Keep local content and title edits when remote content changes.' );
	sync_check( 1 === $report['et/exposition']['preserved'], 'Report preserved local edits.' );
	sync_check( str_contains( get_post_field( 'post_content', $legacy ), 'Local biography before block migration.' ) && str_contains( get_post_field( 'post_content', $manual_translation ), 'Minu enda tõlgitud elulugu.' ), 'Protect migrated local copy on later API changes.' );
	sync_check( false !== strpos( kogo_get_exhibition_content_sections( $ids['et']['exposition'] )['text'], 'Minu toimetatud tekst.' ), 'Read the edited blocks in page helpers.' );
	sync_check( 'Akrüül lõuendil' === get_post_meta( $ids['et']['work'], '_kogo_itgallery_technique', true ), 'Continue updating localized metadata.' );
	$work = $data['et_EE']['works'];
	$data['et_EE']['works'] = array();
	$report = $connector->sync();
	sync_check( 'trash' === get_post_status( $ids['et']['work'] ) && 'publish' === get_post_status( $ids['en']['work'] ), 'Missing Estonian records must not trash the English source.' );
	$data['et_EE']['works'] = $work;
	$report = $connector->sync();
	sync_check( 'publish' === get_post_status( $ids['et']['work'] ) && 0 === array_sum( array_column( $report, 'created' ) ), 'Restore returning records under their original post IDs.' );
	$before = get_post_field( 'post_content', $ids['en']['exposition'] );
	$data['en_EN']['expositions'][0]['description'] = 'Should not be written.';
	$fail = true;
	$report = $connector->sync();
	sync_check( is_wp_error( $report ) && $before === get_post_field( 'post_content', $ids['en']['exposition'] ), 'Any language request failure must stop before catalogue writes.' );
	sync_check( ! get_transient( Kogo_ITGallery::SYNC_LOCK ), 'Release the lock after errors.' );
	echo "Kogo ITGallery WPML integration test passed (6 isolated posts; repeat, edits, relationships, trash/restore, request failure).\n";
} finally {
	remove_filter( 'pre_http_request', $http, 10 );
	foreach ( $types as $type ) {
		foreach ( get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'trash' ), 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) as $id ) { wp_delete_post( $id, true ); }
		unregister_post_type( $type );
	}
	$property->setValue( null, $original_types );
	$sitepress->set_setting( 'custom_posts_sync_option', $previous_settings['custom_posts_sync_option'], true );
	$sitepress->set_setting( 'sync_delete', $previous_settings['sync_delete'] ?? 0, true );
	$sitepress->switch_lang( $previous_language );
	if ( false === $previous_sync ) { delete_option( Kogo_ITGallery::LAST_SYNC ); } else { update_option( Kogo_ITGallery::LAST_SYNC, $previous_sync ); }
}
