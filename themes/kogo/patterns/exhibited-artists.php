<?php
/**
 * Title: Exhibited Artists
 * Slug: kogo/exhibited-artists
 * Categories: kogo, text
 * Viewport Width: 1440
 * Inserter: yes
 * Keywords: artists, exhibitions, years, archive
 * Description: Artists grouped by the opening year of their Kogo exhibitions.
 */
?>
<!-- wp:group {"anchor":"exhibited-artists","align":"full","className":"kogo-emphasized-text kogo-exhibited-artists","layout":{"type":"default"}} -->
<div id="exhibited-artists" class="wp-block-group alignfull kogo-emphasized-text kogo-exhibited-artists">
	<!-- wp:spacer {"height":"64px"} -->
	<div style="height:64px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:columns {"className":"kogo-emphasized-text__columns kogo-exhibited-artists__columns"} -->
	<div class="wp-block-columns kogo-emphasized-text__columns kogo-exhibited-artists__columns">
		<!-- wp:column {"width":"33.333%"} -->
		<div class="wp-block-column" style="flex-basis:33.333%">
			<!-- wp:heading {"level":2,"className":"kogo-exhibited-artists__heading"} -->
			<h2 class="wp-block-heading kogo-exhibited-artists__heading">Exhibited artists</h2>
			<!-- /wp:heading -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"66.667%","className":"kogo-exhibited-artists__content"} -->
		<div class="wp-block-column kogo-exhibited-artists__content" style="flex-basis:66.667%">
			<!-- wp:shortcode -->
			[kogo_exhibited_artists]
			<!-- /wp:shortcode -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:spacer {"height":"80px"} -->
	<div style="height:80px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
</div>
<!-- /wp:group -->
