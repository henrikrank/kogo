<?php
/**
 * Single exhibition page helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the exhibition's date-based status.
 *
 * @param int $post_id Exhibition post ID.
 * @return array
 */
function kogo_get_exhibition_status( $post_id ) {
	$today   = wp_date( 'Y-m-d' );
	$opening = get_post_meta( $post_id, '_kogo_itgallery_opening_date', true );
	$closing = get_post_meta( $post_id, '_kogo_itgallery_closing_date', true );

	if ( $closing && $closing < $today ) {
		return array( 'past', __( 'Past exhibition', 'kogo' ) );
	}

	if ( $opening && $opening > $today ) {
		return array( 'upcoming', __( 'Upcoming exhibition', 'kogo' ) );
	}

	return array( 'current', __( 'Current exhibition', 'kogo' ) );
}

/**
 * Return the remote cover image, falling back to the Featured Image.
 *
 * @param int $post_id Exhibition post ID.
 * @return string
 */
function kogo_get_exhibition_cover_url( $post_id ) {
	$payload = get_post_meta( $post_id, '_kogo_itgallery_payload', true );
	$sizes   = is_array( $payload ) && isset( $payload['main_image_sizes'] ) && is_array( $payload['main_image_sizes'] ) ? $payload['main_image_sizes'] : array();
	$url     = $sizes['url'] ?? ( is_array( $payload ) ? ( $payload['main_image'] ?? '' ) : '' );
	$url     = $url ? $url : get_post_meta( $post_id, '_kogo_itgallery_image_url', true );

	return esc_url_raw( $url ? $url : get_the_post_thumbnail_url( $post_id, 'full' ) );
}

/**
 * Get the imported exhibition venue.
 *
 * @param int $post_id Exhibition post ID.
 * @return string
 */
function kogo_get_exhibition_venue( $post_id ) {
	$location = get_post_meta( $post_id, '_kogo_itgallery_location', true );
	$venue    = is_array( $location ) ? ( $location['text'] ?? $location['name'] ?? '' ) : $location;

	if ( ! $venue && is_array( $location ) && 11385 === (int) ( $location['id'] ?? 0 ) ) {
		$venue = __( 'Kogo Gallery, Tartu', 'kogo' );
	}

	return sanitize_text_field( $venue );
}

/**
 * Normalize an imported image record.
 *
 * @param mixed $image Image record or URL.
 * @return array
 */
function kogo_normalize_exhibition_image( $image ) {
	if ( is_string( $image ) ) {
		$image = array( 'url' => $image );
	}

	if ( ! is_array( $image ) ) {
		return array();
	}

	$original = $image['url'] ?? $image['large_url'] ?? $image['medium_url'] ?? $image['small_url'] ?? '';
	$large    = $image['large_url'] ?? $original;

	if ( ! $original || ! $large ) {
		return array();
	}

	return array(
		'url'    => esc_url_raw( $original ),
		'src'    => esc_url_raw( $large ),
		'small'  => esc_url_raw( $image['small_url'] ?? '' ),
		'medium' => esc_url_raw( $image['medium_url'] ?? '' ),
		'large'  => esc_url_raw( $large ),
	);
}

/**
 * Collect exhibition installation images.
 *
 * @param int $post_id Exhibition post ID.
 * @return array
 */
function kogo_get_exhibition_gallery_images( $post_id ) {
	$payload    = get_post_meta( $post_id, '_kogo_itgallery_payload', true );
	$candidates = array();
	$images     = array();

	if ( is_array( $payload ) && ! empty( $payload['main_image_sizes'] ) ) {
		$candidates[] = $payload['main_image_sizes'];
	}

	$candidates = array_merge( $candidates, (array) get_post_meta( $post_id, '_kogo_itgallery_images', true ) );

	foreach ( $candidates as $candidate ) {
		$image = kogo_normalize_exhibition_image( $candidate );
		if ( $image && ! isset( $images[ $image['url'] ] ) ) {
			$images[ $image['url'] ] = $image;
		}
	}

	if ( ! $images ) {
		$url = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $url ) {
			$images[ $url ] = kogo_normalize_exhibition_image( $url );
		}
	}

	return array_values( $images );
}

/**
 * Split the imported description into the page's editorial sections.
 *
 * The first paragraph is the short idea unless an excerpt is supplied. ITGallery
 * descriptions may also end in standalone TEAM and FUNDING headings.
 *
 * @param string $content Exhibition description.
 * @param string $excerpt Optional hand-written idea.
 * @return array
 */
function kogo_parse_exhibition_content( $content, $excerpt = '' ) {
	$parts   = preg_split( '/(?:\r\n|\r|\n)[\t ]*(?:\r\n|\r|\n)+/u', trim( (string) $content ) );
	$section = 'body';
	$groups  = array(
		'body'    => array(),
		'team'    => array(),
		'funding' => array(),
	);

	foreach ( $parts ? $parts : array() as $part ) {
		$part    = trim( $part );
		$heading = strtoupper( trim( wp_strip_all_tags( $part ) ) );

		if ( 'TEAM' === $heading ) {
			$section = 'team';
			continue;
		}

		if ( in_array( $heading, array( 'FUNDING', 'FUNDING AND SUPPORT', 'FUNDING & SUPPORT' ), true ) ) {
			$section = 'funding';
			continue;
		}

		if ( '' !== $part ) {
			$groups[ $section ][] = $part;
		}
	}

	$idea = trim( (string) $excerpt );
	if ( ! $idea && $groups['body'] ) {
		$idea = array_shift( $groups['body'] );
	}

	return array(
		'idea'    => $idea,
		'text'    => implode( "\n\n", $groups['body'] ),
		'team'    => implode( "\n\n", $groups['team'] ),
		'funding' => implode( "\n\n", $groups['funding'] ),
	);
}

/**
 * Return the parsed description sections for an exhibition.
 *
 * @param int $post_id Exhibition post ID.
 * @return array
 */
function kogo_get_exhibition_content_sections( $post_id ) {
	return kogo_parse_exhibition_content(
		get_post_field( 'post_content', $post_id ),
		get_post_field( 'post_excerpt', $post_id )
	);
}

/**
 * Format a work's imported dimensions.
 *
 * @param mixed $dimensions Imported dimensions.
 * @return string
 */
function kogo_format_work_dimensions( $dimensions ) {
	if ( ! is_array( $dimensions ) ) {
		return '';
	}

	$formatted = array();
	foreach ( $dimensions as $dimension ) {
		if ( ! is_array( $dimension ) ) {
			continue;
		}

		$values = array();
		foreach ( array( 'width', 'height', 'depth' ) as $key ) {
			$value = isset( $dimension[ $key ] ) ? (float) $dimension[ $key ] : 0;
			if ( $value > 0 ) {
				$values[] = rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
			}
		}

		if ( $values ) {
			$unit        = is_array( $dimension['unit'] ?? null ) ? ( $dimension['unit']['name'] ?? '' ) : '';
			$formatted[] = implode( ' × ', $values ) . ( $unit ? ' ' . $unit : '' );
		}
	}

	return implode( '; ', $formatted );
}

/**
 * Render the exhibition cover and key metadata.
 *
 * @return string
 */
function kogo_exhibition_hero_shortcode() {
	$post_id    = get_the_ID();
	$status     = kogo_get_exhibition_status( $post_id );
	$image_url  = kogo_get_exhibition_cover_url( $post_id );
	$date_range = wp_strip_all_tags( kogo_render_exhibition_dates( $post_id ) );
	$artists    = wp_strip_all_tags( kogo_render_exhibition_artists( $post_id ) );
	$venue      = kogo_get_exhibition_venue( $post_id );
	$curator    = sanitize_text_field( get_post_meta( $post_id, 'kogo_exhibition_curator', true ) );
	$details    = array_filter(
		array(
			__( 'Artists', 'kogo' )    => $artists,
			__( 'Curated by', 'kogo' ) => $curator,
			__( 'Venue', 'kogo' )      => $venue,
		)
	);

	ob_start();
	?>
	<section id="intro" class="kogo-exhibition-single__intro">
		<div class="kogo-exhibition-single__hero">
			<?php if ( $image_url ) : ?>
				<img class="kogo-exhibition-single__cover" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" decoding="async" fetchpriority="high">
			<?php endif; ?>
			<div class="kogo-exhibition-single__hero-inner">
				<p class="kogo-exhibition-single__status is-<?php echo esc_attr( $status[0] ); ?>"><span aria-hidden="true"></span><?php echo esc_html( $status[1] ); ?></p>
				<div class="kogo-exhibition-single__hero-content">
					<?php if ( $date_range ) : ?>
						<p class="kogo-exhibition-single__dates"><?php echo esc_html( $date_range ); ?></p>
					<?php endif; ?>
					<h1 class="kogo-exhibition-single__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				</div>
			</div>
		</div>

		<?php if ( $details ) : ?>
			<dl class="kogo-exhibition-single__details">
				<?php foreach ( $details as $label => $value ) : ?>
					<div>
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd><?php echo esc_html( $value ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_hero', 'kogo_exhibition_hero_shortcode' );

/**
 * Render a short exhibition idea using its excerpt or first paragraph.
 *
 * @return string
 */
function kogo_exhibition_idea_shortcode() {
	$sections = kogo_get_exhibition_content_sections( get_the_ID() );

	if ( ! trim( wp_strip_all_tags( $sections['idea'] ) ) ) {
		return '';
	}

	ob_start();
	?>
	<section id="idea" class="kogo-emphasized-text kogo-exhibition-single__idea">
		<div class="wp-block-columns kogo-emphasized-text__columns kogo-exhibition-single__idea-columns">
			<div class="wp-block-column">
				<p class="kogo-emphasized-text__label"><?php esc_html_e( 'The idea', 'kogo' ); ?></p>
			</div>
			<div class="wp-block-column kogo-emphasized-text__content">
				<div class="kogo-emphasized-text__body kogo-exhibition-single__idea-body"><?php echo apply_filters( 'the_content', $sections['idea'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_idea', 'kogo_exhibition_idea_shortcode' );

/**
 * Render the responsive exhibition gallery and its full-size image viewer.
 *
 * @return string
 */
function kogo_exhibition_gallery_shortcode() {
	$post_id = get_the_ID();
	$images  = kogo_get_exhibition_gallery_images( $post_id );

	if ( ! $images ) {
		return '';
	}

	$gallery_data = array();
	foreach ( $images as $index => $image ) {
		$srcset = array_filter(
			array(
				$image['small'] ? $image['small'] . ' 100w' : '',
				$image['medium'] ? $image['medium'] . ' 500w' : '',
				$image['large'] ? $image['large'] . ' 1000w' : '',
			)
		);
		/* translators: 1: Exhibition title, 2: Gallery image number. */
		$alt            = sprintf( __( '%1$s, gallery image %2$d', 'kogo' ), get_the_title( $post_id ), $index + 1 );
		$gallery_data[] = array(
			'full'   => $image['url'],
			'src'    => $image['src'],
			'srcset' => implode( ', ', $srcset ),
			'alt'    => $alt,
		);
	}

	ob_start();
	?>
	<section id="gallery" class="kogo-gallery-grid-wrap kogo-exhibition-single__gallery" aria-label="<?php esc_attr_e( 'Exhibition gallery', 'kogo' ); ?>">
		<div class="kogo-gallery-grid"></div>
		<dialog class="kogo-gallery-lightbox" aria-label="<?php esc_attr_e( 'Exhibition image viewer', 'kogo' ); ?>">
			<button class="kogo-gallery-lightbox__close" type="button" aria-label="<?php esc_attr_e( 'Close image viewer', 'kogo' ); ?>"></button>
			<button class="kogo-gallery-lightbox__arrow kogo-gallery-lightbox__arrow--previous" type="button" aria-label="<?php esc_attr_e( 'Previous image', 'kogo' ); ?>"></button>
			<figure class="kogo-gallery-lightbox__figure">
				<img class="kogo-gallery-lightbox__image" alt="">
				<figcaption class="kogo-gallery-lightbox__count" aria-live="polite"></figcaption>
			</figure>
			<button class="kogo-gallery-lightbox__arrow kogo-gallery-lightbox__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Next image', 'kogo' ); ?>"></button>
		</dialog>
		<script class="kogo-gallery-data" type="application/json"><?php echo wp_json_encode( $gallery_data, JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_gallery', 'kogo_exhibition_gallery_shortcode' );

/**
 * Render the description paragraphs that follow the short idea.
 *
 * @return string
 */
function kogo_exhibition_text_shortcode() {
	$sections = kogo_get_exhibition_content_sections( get_the_ID() );

	if ( ! trim( wp_strip_all_tags( $sections['text'] ) ) ) {
		return '';
	}

	ob_start();
	?>
	<section id="text" class="kogo-exhibition-single__section kogo-exhibition-single__text">
		<div class="wp-block-columns kogo-emphasized-text__columns kogo-exhibition-single__columns">
			<div class="wp-block-column">
				<p class="kogo-emphasized-text__label"><?php esc_html_e( 'Text', 'kogo' ); ?></p>
			</div>
			<div class="wp-block-column kogo-exhibition-single__prose"><?php echo apply_filters( 'the_content', $sections['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_text', 'kogo_exhibition_text_shortcode' );

/**
 * Render works linked to the exhibition as a horizontal slider.
 *
 * @return string
 */
function kogo_exhibition_works_shortcode() {
	$work_post_ids = array_filter( array_map( 'absint', (array) get_post_meta( get_the_ID(), '_kogo_itgallery_work_post_ids', true ) ) );

	if ( ! $work_post_ids ) {
		return '';
	}

	$archive_url = get_post_type_archive_link( 'kogo_work' );
	ob_start();
	?>
	<section id="works" class="kogo-posts-slider kogo-exhibition-works swiper" data-slider-label="<?php esc_attr_e( 'Exhibition works', 'kogo' ); ?>" data-slider-item-label="<?php esc_attr_e( 'works', 'kogo' ); ?>">
		<div class="wp-block-group is-content-justification-space-between is-nowrap is-layout-flex kogo-posts-slider__header">
			<h2 class="kogo-posts-slider__heading"><?php esc_html_e( 'Works', 'kogo' ); ?></h2>
			<div class="wp-block-group is-nowrap is-layout-flex kogo-posts-slider__actions">
				<?php if ( $archive_url ) : ?>
					<div class="wp-block-buttons kogo-posts-slider__more"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'More', 'kogo' ); ?></a></div></div>
				<?php endif; ?>
			</div>
		</div>

		<ul class="wp-block-post-template kogo-posts-slider__items swiper-wrapper">
			<?php foreach ( $work_post_ids as $work_post_id ) : ?>
				<?php
				$payload        = get_post_meta( $work_post_id, '_kogo_itgallery_payload', true );
				$image_url      = function_exists( 'kogo_itgallery_get_image_url' ) ? kogo_itgallery_get_image_url( $work_post_id, 'large' ) : '';
				$image_url      = $image_url ? $image_url : get_the_post_thumbnail_url( $work_post_id, 'large' );
				$artist_post_id = absint( get_post_meta( $work_post_id, '_kogo_itgallery_artist_post_id', true ) );
				$artist         = $artist_post_id ? get_the_title( $artist_post_id ) : '';
				if ( ! $artist && is_array( $payload ) && is_array( $payload['artist'] ?? null ) ) {
					$artist = trim( ( $payload['artist']['name'] ?? '' ) . ' ' . ( $payload['artist']['surname'] ?? '' ) );
				}
				$details = array_filter(
					array(
						get_post_meta( $work_post_id, '_kogo_itgallery_technique', true ),
						kogo_format_work_dimensions( get_post_meta( $work_post_id, '_kogo_itgallery_dimensions', true ) ),
						get_post_meta( $work_post_id, '_kogo_itgallery_year', true ),
					)
				);
				?>
				<li>
					<article class="kogo-posts-slider__card kogo-exhibition-work-card">
						<?php if ( $image_url ) : ?>
							<figure class="kogo-posts-slider__image"><a href="<?php echo esc_url( get_permalink( $work_post_id ) ); ?>"><img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_title( $work_post_id ) ); ?>" loading="lazy" decoding="async"></a></figure>
						<?php endif; ?>
						<?php if ( $artist ) : ?>
							<p class="kogo-exhibition-work-card__artist"><?php echo esc_html( $artist ); ?></p>
						<?php endif; ?>
						<h3 class="kogo-posts-slider__title"><a href="<?php echo esc_url( get_permalink( $work_post_id ) ); ?>"><?php echo esc_html( get_the_title( $work_post_id ) ); ?></a></h3>
						<?php if ( $details ) : ?>
							<p class="kogo-exhibition-work-card__details"><?php echo esc_html( implode( ', ', $details ) ); ?></p>
						<?php endif; ?>
					</article>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_works', 'kogo_exhibition_works_shortcode' );

/**
 * Normalize the synced publication/document metadata.
 *
 * @param int $post_id Exhibition post ID.
 * @return array
 */
function kogo_get_exhibition_documents( $post_id ) {
	$documents = array();

	foreach ( (array) get_post_meta( $post_id, '_kogo_itgallery_documents', true ) as $document ) {
		if ( is_string( $document ) ) {
			$document = array( 'url' => $document );
		}

		if ( ! is_array( $document ) ) {
			continue;
		}

		$url = $document['url'] ?? $document['download_url'] ?? $document['href'] ?? '';
		if ( ! $url && is_array( $document['file'] ?? null ) ) {
			$url = $document['file']['url'] ?? '';
		}

		if ( ! $url ) {
			continue;
		}

		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$label = $document['title'] ?? $document['name'] ?? $document['label'] ?? ( $path ? rawurldecode( basename( $path ) ) : $url );

		$documents[] = array(
			'url'   => esc_url_raw( $url ),
			'label' => sanitize_text_field( $label ),
		);
	}

	return $documents;
}

/**
 * Render exhibition publications supplied by ITGallery or a custom field.
 *
 * @return string
 */
function kogo_exhibition_press_shortcode() {
	$post_id   = get_the_ID();
	$manual    = get_post_meta( $post_id, 'kogo_exhibition_press', true );
	$documents = kogo_get_exhibition_documents( $post_id );

	if ( ! trim( wp_strip_all_tags( (string) $manual ) ) && ! $documents ) {
		return '';
	}

	ob_start();
	?>
	<section id="press" class="kogo-exhibition-single__section">
		<div class="wp-block-columns kogo-emphasized-text__columns kogo-exhibition-single__columns">
			<div class="wp-block-column"><h2 class="kogo-exhibition-single__section-title"><?php esc_html_e( 'Press & publications', 'kogo' ); ?></h2></div>
			<div class="wp-block-column kogo-exhibition-single__prose kogo-exhibition-single__publications">
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
add_shortcode( 'kogo_exhibition_press', 'kogo_exhibition_press_shortcode' );

/**
 * Render short biographies for artists linked to the exhibition.
 *
 * @return string
 */
function kogo_exhibition_bios_shortcode() {
	$bios = array();

	foreach ( (array) get_post_meta( get_the_ID(), '_kogo_itgallery_artist_post_ids', true ) as $artist_post_id ) {
		$artist_post_id = absint( $artist_post_id );
		$name           = get_the_title( $artist_post_id );
		$bio            = trim( wp_strip_all_tags( get_post_field( 'post_content', $artist_post_id ) ) );

		if ( ! $artist_post_id || ! $name || ! $bio ) {
			continue;
		}

		$bio    = preg_replace( '/^' . preg_quote( $name, '/' ) . '\s*/u', '', $bio );
		$bios[] = array(
			'id'   => $artist_post_id,
			'name' => $name,
			'bio'  => wp_trim_words( $bio, 70, '…' ),
		);
	}

	if ( ! $bios ) {
		return '';
	}

	ob_start();
	?>
	<section id="bios" class="kogo-exhibition-single__section">
		<div class="wp-block-columns kogo-emphasized-text__columns kogo-exhibition-single__columns">
			<div class="wp-block-column"><h2 class="kogo-exhibition-single__section-title"><?php esc_html_e( 'Bios', 'kogo' ); ?></h2></div>
			<div class="wp-block-column kogo-exhibition-single__prose kogo-exhibition-single__bios">
				<?php foreach ( $bios as $bio ) : ?>
					<p><strong><?php echo esc_html( $bio['name'] ); ?></strong> <?php echo esc_html( $bio['bio'] ); ?> <a href="<?php echo esc_url( get_permalink( $bio['id'] ) ); ?>"><?php esc_html_e( 'More', 'kogo' ); ?></a></p>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_bios', 'kogo_exhibition_bios_shortcode' );

/**
 * Render team and funding details parsed from the description or custom fields.
 *
 * @return string
 */
function kogo_exhibition_team_shortcode() {
	$post_id  = get_the_ID();
	$sections = kogo_get_exhibition_content_sections( $post_id );
	$team     = get_post_meta( $post_id, 'kogo_exhibition_team', true );
	$funding  = get_post_meta( $post_id, 'kogo_exhibition_funding', true );
	$team     = $team ? $team : $sections['team'];
	$funding  = $funding ? $funding : $sections['funding'];

	if ( ! trim( wp_strip_all_tags( $team ) ) && ! trim( wp_strip_all_tags( $funding ) ) ) {
		return '';
	}

	ob_start();
	?>
	<section id="team" class="kogo-exhibition-single__split">
		<?php if ( $team ) : ?>
			<div>
				<h2 class="kogo-exhibition-single__section-title"><?php esc_html_e( 'Team', 'kogo' ); ?></h2>
				<div class="kogo-exhibition-single__prose"><?php echo apply_filters( 'the_content', $team ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>
		<?php endif; ?>
		<?php if ( $funding ) : ?>
			<div>
				<h2 class="kogo-exhibition-single__section-title"><?php esc_html_e( 'Funding and support', 'kogo' ); ?></h2>
				<div class="kogo-exhibition-single__prose"><?php echo apply_filters( 'the_content', $funding ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kogo_exhibition_team', 'kogo_exhibition_team_shortcode' );
