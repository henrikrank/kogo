<?php
/** Materialize imported copy as native editor blocks, using the existing page sections. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve rich text; unsupported HTML remains editable in a native Classic block. */
function kogo_itgallery_text_blocks( $text ) {
	if ( ! trim( (string) $text ) ) {
		return '';
	}
	if ( has_blocks( $text ) ) {
		return $text;
	}
	$html = wpautop( wp_kses_post( $text ) );
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><div id="kogo-copy">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$blocks = '';
	foreach ( $document->getElementById( 'kogo-copy' )->childNodes as $node ) {
		if ( ! trim( $document->saveHTML( $node ) ) ) {
			continue;
		}
		$tag = strtolower( $node->nodeName );
		if ( 'p' === $tag ) {
			$attrs = $node->hasAttribute( 'class' ) ? array( 'className' => $node->getAttribute( 'class' ) ) : array();
			$blocks .= get_comment_delimited_block_content( $node->attributes->length > count( $attrs ) ? 'core/freeform' : 'core/paragraph', $attrs, $document->saveHTML( $node ) );
		} elseif ( preg_match( '/^h([1-6])$/', $tag, $match ) ) {
			$attrs = array( 'level' => (int) $match[1] );
			if ( $node->hasAttribute( 'class' ) ) {
				$attrs['className'] = $node->getAttribute( 'class' );
			}
			$node->setAttribute( 'class', trim( 'wp-block-heading ' . $node->getAttribute( 'class' ) ) );
			$blocks .= get_comment_delimited_block_content( 'core/heading', $attrs, $document->saveHTML( $node ) );
		} elseif ( in_array( $tag, array( 'ul', 'ol' ), true ) ) {
			// Classic keeps nested lists and their formatting intact without a custom rich-text parser.
			$blocks .= get_comment_delimited_block_content( 'core/freeform', array(), $document->saveHTML( $node ) );
		} elseif ( XML_TEXT_NODE === $node->nodeType ) {
			$blocks .= get_comment_delimited_block_content( 'core/paragraph', array(), '<p>' . esc_html( $node->textContent ) . '</p>' );
		} else {
			$blocks .= get_comment_delimited_block_content( 'core/freeform', array(), $document->saveHTML( $node ) );
		}
	}
	return $blocks;
}

function kogo_itgallery_group_block( $content, $class, $anchor = '', $tag = 'div' ) {
	$attrs = array( 'tagName' => $tag, 'className' => $class, 'layout' => array( 'type' => 'default' ) );
	if ( $anchor ) {
		$attrs['anchor'] = $anchor;
	}
	$html = '<' . $tag . ( $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '' ) . ' class="' . esc_attr( trim( 'wp-block-group ' . $class ) ) . '">' . $content . '</' . $tag . '>';
	return get_comment_delimited_block_content( 'core/group', $attrs, $html );
}

/** A saved, editable label and copy column with the original styling and anchors. */
function kogo_itgallery_copy_section( $text, $label, $key, $class, $columns_class, $copy_class, $anchor ) {
	if ( ! trim( wp_strip_all_tags( $text ) ) ) {
		return '';
	}
	$label_block = get_comment_delimited_block_content( 'core/paragraph', array( 'className' => 'kogo-emphasized-text__label' ), '<p class="kogo-emphasized-text__label">' . esc_html( $label ) . '</p>' );
	$copy = kogo_itgallery_group_block( kogo_itgallery_text_blocks( $text ), 'kogo-imported-copy--' . $key . ' ' . $copy_class );
	$columns = get_comment_delimited_block_content( 'core/column', array(), '<div class="wp-block-column">' . $label_block . '</div>' );
	$columns .= get_comment_delimited_block_content( 'core/column', array(), '<div class="wp-block-column">' . $copy . '</div>' );
	$columns = get_comment_delimited_block_content( 'core/columns', array( 'className' => $columns_class ), '<div class="wp-block-columns ' . esc_attr( $columns_class ) . '">' . $columns . '</div>' );
	return kogo_itgallery_group_block( $columns, $class, $anchor, 'section' );
}

function kogo_itgallery_shortcode_block( $name ) {
	return get_comment_delimited_block_content( 'core/shortcode', array(), '[' . $name . ']' );
}

function kogo_itgallery_page_blocks( $kind, $content, $excerpt = '', $locale = 'en_EN', $post_id = 0, $item = array() ) {
	$et = 'et' === substr( $locale, 0, 2 );
	if ( 'work' === $kind ) {
		$blocks = kogo_itgallery_text_blocks( $content );
		$details = array(
			( $et ? 'Tehnika' : 'Technique' ) => $item['technique'] ?? '',
			( $et ? 'Mõõtmed' : 'Dimensions' ) => kogo_format_work_dimensions( $item['dimensions'] ?? array() ),
			( $et ? 'Aasta' : 'Year' ) => $item['year'] ?? '',
		);
		foreach ( $details as $label => $value ) {
			if ( $value ) {
				$blocks .= get_comment_delimited_block_content( 'core/paragraph', array(), '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>' );
			}
		}
		return $blocks;
	}
	if ( 'artist' === $kind ) {
		$sections = kogo_parse_artist_bio( $content, $excerpt );
		$blocks = kogo_itgallery_shortcode_block( 'kogo_artist_hero' ) . kogo_itgallery_shortcode_block( 'kogo_artist_section_nav' );
		$blocks .= kogo_itgallery_copy_section( $sections['lead'], $et ? 'Olemus' : 'In essence', 'lead', 'kogo-emphasized-text kogo-artist-single__essence', 'kogo-emphasized-text__columns kogo-artist-single__columns', 'kogo-emphasized-text__body', 'in-essence' );
		$blocks .= kogo_itgallery_shortcode_block( 'kogo_artist_gallery' );
		$blocks .= kogo_itgallery_copy_section( $sections['body'], $et ? 'Tutvustus' : 'Introduction', 'body', 'kogo-artist-single__section kogo-artist-single__introduction', 'kogo-emphasized-text__columns kogo-artist-single__columns', 'kogo-artist-single__prose', 'introduction' );
		return $blocks . kogo_itgallery_shortcode_block( 'kogo_artist_press' ) . kogo_itgallery_shortcode_block( 'kogo_artist_works' );
	}
	$sections = kogo_parse_exhibition_content( $content, $excerpt );
	$blocks = kogo_itgallery_shortcode_block( 'kogo_exhibition_hero' );
	$blocks .= kogo_itgallery_copy_section( $sections['idea'], $et ? 'Idee' : 'The idea', 'idea', 'kogo-emphasized-text kogo-exhibition-single__idea', 'kogo-emphasized-text__columns kogo-exhibition-single__idea-columns', 'kogo-emphasized-text__body kogo-exhibition-single__idea-body', 'idea' );
	$blocks .= kogo_itgallery_shortcode_block( 'kogo_exhibition_gallery' );
	$blocks .= kogo_itgallery_copy_section( $sections['text'], $et ? 'Tekst' : 'Text', 'text', 'kogo-exhibition-single__section kogo-exhibition-single__text', 'kogo-emphasized-text__columns kogo-exhibition-single__columns', 'kogo-exhibition-single__prose', 'text' );
	foreach ( array( 'events', 'works', 'press', 'bios' ) as $widget ) {
		$blocks .= kogo_itgallery_shortcode_block( 'kogo_exhibition_' . $widget );
	}
	$team = '';
	foreach ( array( 'team' => $et ? 'Meeskond' : 'Team', 'funding' => $et ? 'Rahastus ja toetajad' : 'Funding and support' ) as $key => $label ) {
		$text = $post_id ? get_post_meta( $post_id, 'kogo_exhibition_' . $key, true ) : '';
		$text = $text ?: $sections[ $key ];
		if ( trim( wp_strip_all_tags( $text ) ) ) {
			$heading = get_comment_delimited_block_content( 'core/heading', array( 'className' => 'kogo-exhibition-single__section-title' ), '<h2 class="wp-block-heading kogo-exhibition-single__section-title">' . esc_html( $label ) . '</h2>' );
			$team .= kogo_itgallery_group_block( $heading . kogo_itgallery_group_block( kogo_itgallery_text_blocks( $text ), 'kogo-imported-copy--' . $key . ' kogo-exhibition-single__prose' ), '' );
		}
	}
	if ( $team ) {
		$blocks .= kogo_itgallery_group_block( $team, 'kogo-exhibition-single__split', 'team', 'section' );
	}
	return $blocks . kogo_itgallery_shortcode_block( 'kogo_imported_related_exhibitions' );
}

/** Read the actual edited blocks for navigation, artist bios and curator credits. */
function kogo_itgallery_saved_copy( $post_id, $keys ) {
	if ( ! get_post_meta( $post_id, '_kogo_itgallery_content_hash', true ) ) {
		return null; // Legacy/manual posts keep their existing template behavior.
	}
	$copy = array_fill_keys( $keys, '' );
	$visit = static function ( $blocks ) use ( &$visit, &$copy ) {
		foreach ( $blocks as $block ) {
			$classes = explode( ' ', $block['attrs']['className'] ?? '' );
			foreach ( $copy as $key => $unused ) {
				if ( in_array( 'kogo-imported-copy--' . $key, $classes, true ) ) {
					$copy[ $key ] .= serialize_blocks( $block['innerBlocks'] );
				}
			}
			$visit( $block['innerBlocks'] );
		}
	};
	$visit( parse_blocks( get_post_field( 'post_content', $post_id ) ) );
	return $copy;
}

/** Use a native Post Content block for materialized pages, retaining legacy templates. */
function kogo_itgallery_render_saved_page( $rendered, $block ) {
	$classes = explode( ' ', $block['attrs']['className'] ?? '' );
	$type = get_post_type();
	$class = array( 'kogo_artist' => 'kogo-artist-single', 'kogo_exposition' => 'kogo-exhibition-single' )[ $type ] ?? '';
	if ( null !== $rendered || is_admin() || ! $class || 'core/group' !== $block['blockName'] || 'main' !== ( $block['attrs']['tagName'] ?? '' ) || ! in_array( $class, $classes, true ) || ! get_post_meta( get_the_ID(), '_kogo_itgallery_content_hash', true ) ) {
		return $rendered;
	}
	return '<main class="wp-block-group alignfull ' . esc_attr( $class ) . '">' . render_block( array( 'blockName' => 'core/post-content', 'attrs' => array( 'layout' => array( 'type' => 'default' ) ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ) . '</main>';
}
add_filter( 'pre_render_block', 'kogo_itgallery_render_saved_page', 10, 2 );

function kogo_itgallery_related_exhibitions_shortcode() {
	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'kogo/linked-exhibitions' );
	$content = $pattern['content'] ?? '';
	foreach ( array( 'Related exhibitions', 'More' ) as $label ) {
		$content = str_replace( '>' . esc_html( $label ) . '<', '>' . esc_html( __( $label, 'kogo' ) ) . '<', $content );
	}
	return do_blocks( $content );
}
add_shortcode( 'kogo_imported_related_exhibitions', 'kogo_itgallery_related_exhibitions_shortcode' );

/** Artist links and artwork images use local metadata, so they follow the synced language. */
function kogo_itgallery_work_artist_shortcode() {
	$id = absint( get_post_meta( get_the_ID(), '_kogo_itgallery_artist_post_id', true ) );
	return $id ? '<p><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></p>' : '';
}
add_shortcode( 'kogo_work_artist', 'kogo_itgallery_work_artist_shortcode' );

function kogo_itgallery_work_featured_image( $content, $block, $instance ) {
	$id = $instance->context['postId'] ?? get_the_ID();
	if ( $content || 'kogo_work' !== get_post_type( $id ) ) {
		return $content;
	}
	$url = kogo_itgallery_get_image_url( $id, 'large' );
	return $url ? '<figure class="wp-block-post-featured-image kogo-single-post__image"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( get_the_title( $id ) ) . '" decoding="async"></figure>' : '';
}
add_filter( 'render_block_core/post-featured-image', 'kogo_itgallery_work_featured_image', 10, 3 );
