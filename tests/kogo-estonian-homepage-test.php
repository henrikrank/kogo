<?php
/** Run against Local WPML: wp eval-file tests/kogo-estonian-homepage-test.php */
global $sitepress, $wpdb;
$sitepress->switch_lang( 'en' );
$source_id = (int) get_option( 'page_on_front' );
$target_id = apply_filters( 'wpml_object_id', $source_id, 'page', false, 'et' );
function home_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
home_check( $target_id && $target_id !== $source_id, 'The homepage needs a distinct WPML Estonian translation.' );
home_check( 'publish' === get_post_status( $target_id ), 'Publish the Estonian homepage.' );
$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $target_id, 'element_type' => 'post_page' ) );
home_check( 'et' === $details->language_code && 'en' === $details->source_language_code, 'Connect the Estonian page to its English source.' );
foreach ( array( 3, 5 ) as $category_id ) {
	$language = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type='tax_category'", $category_id ) );
	home_check( 'en' === $language, 'Preserve the original English homepage categories.' );
}
$english_url = get_permalink( $source_id );
$estonian_url = $sitepress->language_url( 'et', true );
$documents = array();
foreach ( array( 'en' => $english_url, 'et' => $estonian_url ) as $language => $url ) {
	$response = wp_remote_get( $url );
	home_check( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ), 'Both homepage languages must respond successfully.' );
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8">' . wp_remote_retrieve_body( $response ) );
	libxml_clear_errors();
	$documents[$language] = new DOMXPath( $document );
}
$et = $documents['et'];
$en = $documents['en'];
home_check( 'et' === $et->evaluate( 'string(/html/@lang)' ), 'The Estonian URL must stay in Estonian.' );
$main = $et->evaluate( 'string(//main)' );
foreach ( array( 'Kogost', 'Kogo on Tartus tegutsev', 'Uudised', 'Avasta Kogo programm', 'Kunstnikud', 'Kõik kunstnikud', 'Kunstnik fookuses', 'Loe artiklit', 'installatsioon, fotograafia' ) as $copy ) {
	home_check( false !== strpos( $main, $copy ), 'Missing homepage translation: ' . $copy );
}
home_check( false !== strpos( $en->evaluate( 'string(//main)' ), 'Kogo is a contemporary art gallery' ), 'Keep the English homepage text.' );
home_check( false !== strpos( $et->evaluate( 'string(//footer)' ), 'Liitu meie uudiskirjaga!' ), 'Translate the shared footer.' );
home_check( false !== strpos( $et->evaluate( 'string(//nav[contains(@class,"kogo-header__navigation")])' ), 'Näitused' ), 'Translate the header navigation.' );
home_check( 'EE' === $et->evaluate( 'string(//nav[@class="kogo-header__languages"]/a[@aria-current="page"])' ), 'Mark EE as the active language.' );
home_check( $english_url === $et->evaluate( 'string(//nav[@class="kogo-header__languages"]/a[@lang="en"]/@href)' ), 'Link EN back to the English homepage.' );
foreach ( array( 'kogo-posts-slider__items', 'kogo-artists__grid', 'kogo-post-highlight__items' ) as $class ) {
	$query = 'count(//ul[contains(@class,"' . $class . '")]/li)';
	home_check( $en->evaluate( $query ) > 0 && $et->evaluate( $query ) === $en->evaluate( $query ), 'Preserve the homepage cards: ' . $class );
}
$images = static function ( $xpath ) {
	$urls = array();
	foreach ( $xpath->query( '//main//img/@src' ) as $attribute ) { $urls[] = $attribute->nodeValue; }
	return $urls;
};
home_check( $images( $en ) === $images( $et ), 'Preserve the original homepage images.' );
home_check( false !== strpos( $et->evaluate( 'string(//*[@id="kogo-visibility"])' ), 'Nähtavuse seaded' ), 'Translate visibility settings.' );
echo "Kogo Estonian WPML homepage test passed.\n";
