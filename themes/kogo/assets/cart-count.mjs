/** Bridge Cart/Checkout block quantities to WooCommerce's native cart fragments. */
export function setupCartCount() {
	const data = window.wp?.data;
	const cartStore = window.wc?.wcBlocksData?.cartStore;
	const cart = document.querySelector('.kogo-header__icon-button--cart');
	if (!data || !cartStore || !cart || !window.jQuery) return;

	let count = Number(cart.dataset.cartCount);
	const refresh = () => {
		const store = data.select(cartStore);
		if (!store.hasFinishedResolution('getCartData', [])) return;
		const nextCount = store.getCartData().itemsCount;
		if (nextCount === count) return;
		count = nextCount;
		window.jQuery(document.body).trigger('wc_fragment_refresh');
	};
	data.subscribe(refresh, cartStore);
	refresh();
}
