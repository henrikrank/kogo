<?php

define( 'ABSPATH', __DIR__ . '/' );

function add_filter() {}
function get_queried_object_id() {
	return 42;
}
function get_post_type() {
	return 'post';
}
function wp_get_post_categories() {
	return array( 3, 7 );
}

require dirname( __DIR__ ) . '/themes/kogo/inc/post-single.php';

$unrelated = array( 'posts_per_page' => 10 );
$unchanged = kogo_filter_related_news_query( $unrelated, (object) array( 'context' => array( 'queryId' => 1 ) ) );
$related   = kogo_filter_related_news_query(
	array( 'post__not_in' => array( 11 ) ),
	(object) array( 'context' => array( 'queryId' => KOGO_RELATED_NEWS_QUERY_ID ) )
);

if ( $unrelated !== $unchanged ) {
	fwrite( STDERR, "Unrelated Query blocks must remain unchanged.\n" );
	exit( 1 );
}

if ( array( 11, 42 ) !== $related['post__not_in'] || array( 3, 7 ) !== $related['category__in'] ) {
	fwrite( STDERR, "Related news query does not exclude the current post or match its categories.\n" );
	exit( 1 );
}

echo "Kogo single post test passed.\n";
