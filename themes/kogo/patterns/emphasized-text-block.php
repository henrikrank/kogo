<?php
/**
 * Title: Emphasized Text Block
 * Slug: kogo/emphasized-text-block
 * Categories: kogo, text
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: emphasized text, introduction, about, call to action
 * Description: Two-column introduction with an emphasized paragraph and link button.
 */
?>
<!-- wp:group {"align":"full","className":"kogo-emphasized-text","layout":{"type":"default"}} -->
<div class="wp-block-group alignfull kogo-emphasized-text">
	<!-- wp:spacer {"height":"64px"} -->
	<div style="height:64px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:columns {"className":"kogo-emphasized-text__columns"} -->
	<div class="wp-block-columns kogo-emphasized-text__columns">
		<!-- wp:column {"width":"33.333%"} -->
		<div class="wp-block-column" style="flex-basis:33.333%">
			<!-- wp:paragraph {"fontSize":"small","className":"kogo-emphasized-text__label"} -->
			<p class="kogo-emphasized-text__label has-small-font-size">About Kogo</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"66.667%","className":"kogo-emphasized-text__content"} -->
		<div class="wp-block-column kogo-emphasized-text__content" style="flex-basis:66.667%">
			<!-- wp:paragraph {"className":"kogo-emphasized-text__body"} -->
			<p class="kogo-emphasized-text__body">Kogo is a contemporary art gallery based in Tartu, Estonia. We represent and promote Estonian and Latvian artists while fostering free creative expression, interdisciplinary collaboration, and innovative ideas in the arts and wider society.</p>
			<!-- /wp:paragraph -->

			<!-- wp:buttons {"className":"kogo-emphasized-text__actions"} -->
			<div class="wp-block-buttons kogo-emphasized-text__actions">
				<!-- wp:button {"className":"is-style-outline kogo-emphasized-text__more"} -->
				<div class="wp-block-button is-style-outline kogo-emphasized-text__more"><a class="wp-block-button__link wp-element-button" href="#">More</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:spacer {"height":"64px"} -->
	<div style="height:64px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
</div>
<!-- /wp:group -->
