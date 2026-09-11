<?php
/**
 * Single artist page helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Count words without losing names or non-ASCII characters.
 *
 * @param string $text Text to count.
 * @return int
 */
function kogo_artist_word_count( $text ) {
	$result = preg_match_all( '/[\p{L}\p{N}]+(?:[\x{2019}\x{0027}-][\p{L}\p{N}]+)*/u', wp_strip_all_tags( (string) $text ), $matches );
	return false === $result ? 0 : $result;
}

/**
 * Keep the emphasized artist introduction compact and sentence-aware.
 *
 * @param string $text      Introduction text.
 * @param int    $max_words Maximum lead length.
 * @return array
 */
function kogo_split_artist_lead( $text, $max_words = 55 ) {
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
	if ( ! $text || kogo_artist_word_count( $text ) <= $max_words ) {
		return array( $text, '' );
	}

	$sentences = preg_split( '/(?<=[.!?])\s+(?=[\p{Lu}\p{N}\x{201C}"\x{00AB}])/u', $text ) ?: array( $text );
	$lead      = array();
	$used      = 0;

	foreach ( $sentences as $index => $sentence ) {
		$sentence_words = kogo_artist_word_count( $sentence );
		if ( ! $lead && $sentence_words > $max_words ) {
			$words     = preg_split( '/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
			$remainder = implode( ' ', array_slice( $words, $max_words ) );
			$tail      = implode( ' ', array_slice( $sentences, $index + 1 ) );
			return array( implode( ' ', array_slice( $words, 0, $max_words ) ), trim( $remainder . ' ' . $tail ) );
		}

		if ( $lead && $used + $sentence_words > $max_words ) {
			return array( implode( ' ', $lead ), implode( ' ', array_slice( $sentences, $index ) ) );
		}

		$lead[] = $sentence;
		$used  += $sentence_words;
		if ( $used >= $max_words ) {
			return array( implode( ' ', $lead ), implode( ' ', array_slice( $sentences, $index + 1 ) ) );
		}
	}

	return array( implode( ' ', $lead ), '' );
}

/**
 * Split an imported biography into its lead and continuation.
 *
 * @param string $content Artist biography.
 * @param string $excerpt Optional hand-written lead.
 * @return array
 */
function kogo_parse_artist_bio( $content, $excerpt = '' ) {
	$content = trim( (string) $content );
	$excerpt = trim( (string) $excerpt );

	if ( $excerpt ) {
		list( $lead, $overflow ) = kogo_split_artist_lead( $excerpt );
		return array(
			'lead' => $lead,
			'body' => $content ?: $overflow,
		);
	}

	$paragraphs = array();
	if ( preg_match_all( '/<p\b[^>]*>.*?<\/p>/is', $content, $matches ) ) {
		$paragraphs = array_values(
			array_filter(
				array_map( 'trim', $matches[0] ),
				static function ( $paragraph ) {
					return '' !== trim( wp_strip_all_tags( $paragraph ) );
				}
			)
		);
	} else {
		$paragraphs = array_values( array_filter( preg_split( '/(?:\r\n|\r|\n){2,}/', $content ) ?: array() ) );
	}

	$lead_source = array_shift( $paragraphs ) ?: '';
	list( $lead, $overflow ) = kogo_split_artist_lead( $lead_source );

	if ( $overflow ) {
		array_unshift( $paragraphs, '<p>' . htmlspecialchars( $overflow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</p>' );
		$lead = '<p>' . htmlspecialchars( $lead, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</p>';
	} else {
		$lead = $lead_source;
	}

	return array(
		'lead' => $lead,
		'body' => implode( "\n", $paragraphs ),
	);
}

/**
 * Return the biography sections for an artist.
 *
 * @param int $post_id Artist post ID.
 * @return array
 */
function kogo_get_artist_bio_sections( $post_id ) {
	return kogo_parse_artist_bio(
		get_post_field( 'post_content', $post_id ),
		get_post_field( 'post_excerpt', $post_id )
	);
}

/**
 * Return the imported portrait and responsive sources.
 *
 * @param int $post_id Artist post ID.
 * @return array
 */
function kogo_get_artist_portrait( $post_id ) {
	$payload = get_post_meta( $post_id, '_kogo_itgallery_payload', true );
	$sizes   = is_array( $payload ) && is_array( $payload['photo_sizes'] ?? null ) ? $payload['photo_sizes'] : array();
	$src     = $sizes['large'] ?? $sizes['medium'] ?? $sizes['url'] ?? '';
	$src     = $src ? $src : kogo_itgallery_get_image_url( $post_id, 'large' );

	if ( ! $src ) {
		$src = get_the_post_thumbnail_url( $post_id, 'large' );
	}

	$srcset = array_filter(
		array(
			! empty( $sizes['small'] ) ? esc_url_raw( $sizes['small'] ) . ' 100w' : '',
			! empty( $sizes['medium'] ) ? esc_url_raw( $sizes['medium'] ) . ' 500w' : '',
			! empty( $sizes['large'] ) ? esc_url_raw( $sizes['large'] ) . ' 1000w' : '',
		)
	);

	return array(
		'src'    => esc_url_raw( $src ),
		'srcset' => implode( ', ', $srcset ),
	);
}

/**
 * Collect gallery images from exhibitions linked to an artist.
 *
 * @param int $post_id Artist post ID.
 * @return array
 */
function kogo_get_artist_gallery_images( $post_id ) {
	static $cache = array();

	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$images = array();
	foreach ( (array) get_post_meta( $post_id, '_kogo_itgallery_exposition_post_ids', true ) as $exhibition_id ) {
		foreach ( kogo_get_exhibition_gallery_images( absint( $exhibition_id ) ) as $image ) {
			if ( ! empty( $image['url'] ) ) {
				$images[ $image['url'] ] = $image;
			}
		}
	}

	$cache[ $post_id ] = array_values( $images );
	return $cache[ $post_id ];
}

/**
 * Return published works linked to an artist.
 *
 * @param int $post_id Artist post ID.
 * @return array
 */
function kogo_get_artist_work_ids( $post_id ) {
	return array_values(
		array_filter(
			array_map( 'absint', (array) get_post_meta( $post_id, '_kogo_itgallery_work_post_ids', true ) ),
			static function ( $work_id ) {
				return 'publish' === get_post_status( $work_id );
			}
		)
	);
}

/**
 * Render the artist hero using the imported Cloudflare/CloudFront portrait.
 *
 * @return string
 */
function kogo_artist_hero_shortcode() {
	$post_id     = get_the_ID();
	$portrait    = kogo_get_artist_portrait( $post_id );
	$disciplines = implode( ', ', kogo_get_artist_disciplines( $post_id ) );

	ob_start();
	?>
	<section class="kogo-artist-single__hero" aria-labelledby="artist-title">
		<div class="kogo-artist-single__hero-columns">
			<div class="kogo-artist-single__hero-intro">
				<p class="kogo-artist-single__label"><?php esc_html_e( 'Artist', 'kogo' ); ?></p>
				<h1 id="artist-title" class="kogo-artist-single__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<?php if ( $disciplines ) : ?>
					<p class="kogo-artist-single__disciplines"><?php echo esc_html( $disciplines ); ?></p>
				<?php endif; ?>
			</div>
			<div class="kogo-artist-single__hero-media">
				<?php if ( $portrait['src'] ) : ?>
					<figure class="kogo-artist-single__portrait"><img src="<?php echo esc_url( $portrait['src'] ); ?>"<?php echo $portrait['srcset'] ? ' srcset="' . esc_attr( $portrait['srcset'] ) . '" sizes="(max-width: 782px) 100vw, 50vw"' : ''; ?> alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" decoding="async" fetchpriority="high"></figure>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_artist_hero', 'kogo_artist_hero_shortcode' );

/**
 * Render a reusable menu of in-page section anchors.
 *
 * @param array  $sections Section ID => label pairs.
 * @param string $label    Accessible menu label.
 * @return string
 */
function kogo_render_section_nav( $sections, $label ) {
	$sections = array_filter( $sections );
	if ( ! $sections ) {
		return '';
	}

	$html = '<nav class="kogo-section-nav" aria-label="' . esc_attr( $label ) . '"><div class="kogo-section-nav__scroller"><ul class="kogo-section-nav__list">';
	foreach ( $sections as $id => $section_label ) {
		$html .= '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $section_label ) . '</a></li>';
	}
	return $html . '</ul></div></nav>';
}

/**
 * Render the artist page anchor navigation.
 *
 * @return string
 */
function kogo_artist_section_nav_shortcode() {
	$post_id  = get_the_ID();
	$bio      = kogo_get_artist_bio_sections( $post_id );
	$manual   = get_post_meta( $post_id, 'kogo_artist_press', true );
	$sections = array(
			'in-essence'  => $bio['lead'] ? __( 'In essence', 'kogo' ) : '',
			'gallery'     => kogo_get_artist_gallery_images( $post_id ) ? __( 'Gallery', 'kogo' ) : '',
			'introduction' => $bio['body'] ? __( 'Introduction', 'kogo' ) : '',
			'press'       => ( $manual || kogo_get_exhibition_documents( $post_id ) ) ? __( 'Press & publications', 'kogo' ) : '',
			'works'       => kogo_get_artist_work_ids( $post_id ) ? __( 'Works', 'kogo' ) : '',
		);

	return kogo_render_section_nav( $sections, __( 'Artist page sections', 'kogo' ) );
}
add_shortcode( 'kogo_artist_section_nav', 'kogo_artist_section_nav_shortcode' );

/**
 * Render the lead biography.
 *
 * @return string
 */
function kogo_artist_essence_shortcode() {
	$lead = kogo_get_artist_bio_sections( get_the_ID() )['lead'];
	if ( ! trim( wp_strip_all_tags( $lead ) ) ) {
		return '';
	}

	ob_start();
	?>
	<section id="in-essence" class="kogo-emphasized-text kogo-artist-single__essence">
		<div class="wp-block-columns kogo-emphasized-text__columns kogo-artist-single__columns">
			<div class="wp-block-column"><p class="kogo-emphasized-text__label"><?php esc_html_e( 'In essence', 'kogo' ); ?></p></div>
			<div class="wp-block-column kogo-emphasized-text__content"><div class="kogo-emphasized-text__body"><?php echo apply_filters( 'the_content', $lead ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_artist_essence', 'kogo_artist_essence_shortcode' );

/**
 * Render gallery images from the artist's linked exhibitions.
 *
 * @return string
 */
function kogo_artist_gallery_shortcode() {
	$post_id = get_the_ID();
	return kogo_render_gallery(
		kogo_get_artist_gallery_images( $post_id ),
		sprintf( __( '%s gallery', 'kogo' ), get_the_title( $post_id ) ),
		'kogo-artist-single__gallery'
	);
}
add_shortcode( 'kogo_artist_gallery', 'kogo_artist_gallery_shortcode' );

/**
 * Render the rest of the biography with a progressively enhanced disclosure.
 *
 * @return string
 */
function kogo_artist_introduction_shortcode() {
	$body = kogo_get_artist_bio_sections( get_the_ID() )['body'];
	if ( ! trim( wp_strip_all_tags( $body ) ) ) {
		return '';
	}

	$plain   = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $body ) ) );
	$is_long = kogo_artist_word_count( $plain ) > 180;
	$preview = $is_long ? wp_trim_words( $plain, 120, '…' ) : '';

	ob_start();
	?>
	<section id="introduction" class="kogo-artist-single__section kogo-artist-single__introduction">
		<div class="kogo-artist-single__prose"<?php echo $is_long ? ' data-artist-bio' : ''; ?>>
			<?php if ( $is_long ) : ?><div class="kogo-artist-single__bio-preview" hidden><p><?php echo esc_html( $preview ); ?></p></div><?php endif; ?>
			<div class="kogo-artist-single__bio-full"><?php echo apply_filters( 'the_content', $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php if ( $is_long ) : ?><button class="kogo-artist-single__bio-toggle" type="button" aria-expanded="false" hidden><?php esc_html_e( 'Show full text', 'kogo' ); ?></button><?php endif; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_artist_introduction', 'kogo_artist_introduction_shortcode' );

/**
 * Render artist publications supplied by ITGallery or a manual custom field.
 *
 * @return string
 */
function kogo_artist_press_shortcode() {
	$post_id   = get_the_ID();
	$manual    = get_post_meta( $post_id, 'kogo_artist_press', true );
	$documents = kogo_get_exhibition_documents( $post_id );
	if ( ! trim( wp_strip_all_tags( (string) $manual ) ) && ! $documents ) {
		return '';
	}

	ob_start();
	?>
	<section id="press" class="kogo-artist-single__section kogo-artist-single__press">
		<div class="kogo-artist-single__columns">
			<h2 class="kogo-artist-single__section-title"><?php esc_html_e( 'Press & publications', 'kogo' ); ?></h2>
			<div class="kogo-artist-single__prose kogo-artist-single__publications">
				<?php if ( $manual ) : ?>
					<?php echo apply_filters( 'the_content', $manual ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
				<?php foreach ( $documents as $document ) : ?>
					<p><a href="<?php echo esc_url( $document['url'] ); ?>"><?php echo esc_html( $document['label'] ); ?></a></p>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_artist_press', 'kogo_artist_press_shortcode' );

/**
 * Render the artist's works as a four-column grid.
 *
 * @return string
 */
function kogo_artist_works_shortcode() {
	$work_post_ids = kogo_get_artist_work_ids( get_the_ID() );
	if ( ! $work_post_ids ) {
		return '';
	}

	ob_start();
	?>
	<section id="works" class="kogo-artist-single__section kogo-artist-works" data-artist-works data-initial-items="12" data-page-size="8" aria-labelledby="artist-works-title">
		<h2 id="artist-works-title" class="kogo-artist-single__section-title"><?php esc_html_e( 'Works', 'kogo' ); ?></h2>
		<ul class="kogo-artist-works__grid">
			<?php foreach ( $work_post_ids as $work_post_id ) : ?>
				<li><?php echo kogo_render_work_card( $work_post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
			<?php endforeach; ?>
		</ul>
		<div class="wp-block-button is-style-outline kogo-artist-works__more"><button class="wp-block-button__link wp-element-button" type="button" hidden><?php esc_html_e( 'Load more', 'kogo' ); ?></button></div>
		<p class="screen-reader-text" aria-live="polite"></p>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_artist_works', 'kogo_artist_works_shortcode' );
