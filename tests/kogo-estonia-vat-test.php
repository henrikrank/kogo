<?php
// Run with WordPress loaded: wp eval-file tests/kogo-estonia-vat-test.php
// Read-only check: no orders, cart sessions or product data are saved.
if ( array( 'EE' ) !== array_keys( WC()->countries->get_shipping_countries() ) ) {
	throw new RuntimeException( 'Only Estonian delivery addresses should be allowed.' );
}
if ( 'EE' !== WC()->countries->get_base_country() || ! wc_tax_enabled() || ! wc_prices_include_tax() || 'incl' !== get_option( 'woocommerce_tax_display_shop' ) || 'incl' !== get_option( 'woocommerce_tax_display_cart' ) ) {
	throw new RuntimeException( 'The Estonian shop and cart must display VAT-inclusive prices.' );
}
foreach ( array_keys( WC()->countries->get_allowed_countries() ) as $billing_country ) {
	$customer = new WC_Customer( 0 );
	$customer->set_billing_country( $billing_country );
	$customer->set_shipping_country( 'EE' );
	$included_vat = 0;
	foreach ( array( '' => 24.0, 'books' => 9.0 ) as $class => $percentage ) {
		$rates = WC_Tax::get_rates( $class, $customer );
		if ( 1 !== count( $rates ) || $percentage !== (float) reset( $rates )['rate'] || 'EE' !== WC_Tax::get_tax_location( $class, $customer )[0] ) {
			throw new RuntimeException( 'Incorrect domestic VAT for an Estonian delivery with billing country ' . $billing_country );
		}
		$included_vat += round( array_sum( WC_Tax::calc_inclusive_tax( 15.50, $rates ) ), 2 );
	}
	if ( abs( 4.28 - $included_vat ) > 0.001 ) {
		throw new RuntimeException( 'A 15.50 book and a 15.50 standard product must include 4.28 VAT.' );
	}
}
foreach ( array( 'LV', 'LT', 'FI', 'PL' ) as $country ) {
	$zone = WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => array( 'country' => $country, 'state' => '', 'postcode' => '' ) ) );
	if ( $zone->get_shipping_methods( true ) ) { throw new RuntimeException( 'International shipping methods should not be available.' ); }
}
$domestic_zone = WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => array( 'country' => 'EE', 'state' => '', 'postcode' => '' ) ) );
if ( ! $domestic_zone->get_shipping_methods( true ) ) { throw new RuntimeException( 'Domestic shipping must remain available.' ); }
for ( $number = 1; $number <= 12; ++$number ) {
	$product = wc_get_product( wc_get_product_id_by_sku( sprintf( 'KOGO-SAMPLE-%03d', $number ) ) );
	$expected_class = in_array( $number, array( 5, 6 ), true ) ? '' : 'books';
	if ( ! $product || $expected_class !== $product->get_tax_class() || '15.50' !== $product->get_regular_price() || ( 2 === $number ? '9.00' : '' ) !== $product->get_sale_price() ) {
		throw new RuntimeException( 'Sample product VAT classes and prices must remain correct.' );
	}
	if ( abs( (float) $product->get_price() - wc_get_price_including_tax( $product ) ) > 0.001 ) {
		throw new RuntimeException( 'Customer-facing sample product prices must remain unchanged.' );
	}
}
echo "Estonia-only shipping and domestic VAT checks passed.\n";
