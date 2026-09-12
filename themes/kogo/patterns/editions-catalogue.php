<?php
/**
 * Title: Editions catalogue
 * Slug: kogo/editions-catalogue
 * Categories: kogo
 * Inserter: no
 */
?>
<!-- wp:group {"tagName":"main","align":"full","className":"kogo-editions","layout":{"type":"default"}} -->
<main class="wp-block-group alignfull kogo-editions">
	<!-- wp:group {"className":"kogo-editions__intro","layout":{"type":"default"}} -->
	<div class="wp-block-group kogo-editions__intro">
		<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"kogo-editions__title"} /-->
		<!-- wp:shortcode -->[kogo_editions_intro]<!-- /wp:shortcode -->
	</div>
	<!-- /wp:group -->
	<!-- wp:separator {"className":"kogo-editions__divider"} --><hr class="wp-block-separator has-alpha-channel-opacity kogo-editions__divider"/><!-- /wp:separator -->
	<!-- wp:group {"className":"kogo-editions__catalogue","layout":{"type":"default"}} -->
	<div class="wp-block-group kogo-editions__catalogue">
	<!-- wp:group {"tagName":"aside","className":"kogo-editions__filters","layout":{"type":"default"}} -->
	<aside class="wp-block-group kogo-editions__filters">
		<!-- wp:shortcode -->[kogo_editions_categories]<!-- /wp:shortcode -->
	</aside>
	<!-- /wp:group -->
	<!-- wp:group {"className":"kogo-editions__results","layout":{"type":"default"}} -->
	<div class="wp-block-group kogo-editions__results">
	<!-- wp:woocommerce/store-notices /-->
	<!-- wp:woocommerce/product-collection {"queryId":90406,"query":{"woocommerceAttributes":[],"woocommerceStockStatus":["instock","outofstock","onbackorder"],"taxQuery":{},"isProductCollectionBlock":true,"perPage":12,"pages":0,"offset":0,"postType":"product","order":"asc","orderBy":"menu_order","author":"","search":"","exclude":[],"sticky":"","inherit":true},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":true},"queryContextIncludes":["collection"],"className":"kogo-editions__products"} -->
	<div class="wp-block-woocommerce-product-collection kogo-editions__products">
		<!-- wp:woocommerce/product-template {"className":"kogo-editions__grid"} -->
			<!-- wp:group {"className":"kogo-edition-card__media","layout":{"type":"default"}} -->
			<div class="wp-block-group kogo-edition-card__media">
				<!-- wp:woocommerce/product-image {"showSaleBadge":false,"imageSizing":"single","scale":"contain","aspectRatio":"1","isDescendentOfQueryLoop":true,"className":"kogo-edition-card__image"} /-->
				<!-- wp:woocommerce/product-button {"isDescendentOfQueryLoop":true,"className":"kogo-edition-card__cart"} /-->
			</div>
			<!-- /wp:group -->
			<!-- wp:post-title {"textAlign":"center","level":2,"isLink":true,"className":"kogo-edition-card__title","__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
			<!-- wp:woocommerce/product-price {"textAlign":"center","isDescendentOfQueryLoop":true,"className":"kogo-edition-card__price"} /-->
		<!-- /wp:woocommerce/product-template -->
		<!-- wp:query-pagination {"paginationArrow":"arrow","showLabel":false,"className":"kogo-editions__pagination","layout":{"type":"flex","justifyContent":"center"}} -->
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		<!-- /wp:query-pagination -->
		<!-- wp:woocommerce/product-collection-no-results -->
			<!-- wp:paragraph --><p>No editions found.</p><!-- /wp:paragraph -->
		<!-- /wp:woocommerce/product-collection-no-results -->
	</div>
	<!-- /wp:woocommerce/product-collection -->
	</div>
	<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->
