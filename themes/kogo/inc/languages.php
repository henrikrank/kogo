<?php
/** Connect the existing header switcher to WPML's language and translation URLs. */
function kogo_header_languages( $content ) {
	if ( false === strpos( $content, 'kogo-header__languages' ) ) {
		return $content;
	}
	// WPML links missing translations to that language's homepage.
	$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
	if ( empty( $languages ) ) {
		return $content;
	}

	$links = '';
	foreach ( $languages as $language ) {
		$code = $language['language_code'];
		$links .= sprintf(
			'<a href="%1$s" lang="%2$s" hreflang="%2$s" aria-label="%3$s"%4$s>%5$s</a>',
			esc_url( $language['url'] ),
			esc_attr( $code ),
			esc_attr( $language['native_name'] ),
			$language['active'] ? ' aria-current="page"' : '',
			esc_html( 'et' === $code ? 'EE' : strtoupper( $code ) )
		);
	}
	return preg_replace_callback(
		'/<nav\b[^>]*class="kogo-header__languages"[^>]*>.*?<\/nav>/s',
		static function () use ( $links ) {
			return '<nav class="kogo-header__languages" aria-label="' . esc_attr__( 'Language', 'kogo' ) . '">' . $links . '</nav>';
		},
		$content
	);
}
add_filter( 'render_block_core/html', 'kogo_header_languages' );
