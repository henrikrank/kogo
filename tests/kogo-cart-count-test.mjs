// Run with: node tests/kogo-cart-count-test.mjs
import assert from 'node:assert/strict';
import { setupCartCount } from '../themes/kogo/assets/cart-count.mjs';

globalThis.document = { body: {}, querySelector: () => ({ dataset: { cartCount: '1' } }) };
globalThis.window = {};
assert.doesNotThrow(setupCartCount, 'Non-WooCommerce pages need no block store.');

let count = 0;
let ready = false;
let refreshes = 0;
let onChange;
const cartStore = {};
window.wp = { data: {
	select: (store) => {
		assert.equal(store, cartStore);
		return {
			hasFinishedResolution: (selector, args) => {
				assert.equal(selector, 'getCartData');
				assert.deepEqual(args, []);
				return ready;
			},
			getCartData: () => ({ itemsCount: count }),
		};
	},
	subscribe: (callback, store) => { assert.equal(store, cartStore); onChange = callback; },
} };
window.wc = { wcBlocksData: { cartStore } };
window.jQuery = (body) => {
	assert.equal(body, document.body);
	return { trigger: (event) => { assert.equal(event, 'wc_fragment_refresh'); refreshes++; } };
};

setupCartCount();
assert.equal(refreshes, 0, 'Loading block data must not clear the server-rendered count.');
ready = true;
count = 1;
onChange();
assert.equal(refreshes, 0, 'Matching initial quantities need no additional request.');
count = 2;
onChange();
onChange();
assert.equal(refreshes, 1, 'A quantity update refreshes native fragments only once.');
count = 0;
onChange();
assert.equal(refreshes, 2, 'Removing the last item refreshes the hidden empty badge.');
console.log('Kogo cart count test passed.');
