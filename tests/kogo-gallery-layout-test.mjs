import assert from 'node:assert/strict';
import { getGalleryLayout } from '../themes/kogo/assets/gallery-layout.mjs';

assert.deepEqual(getGalleryLayout(5, 6), { cells: 5, moreIndex: -1, moreCount: 0 });
assert.deepEqual(getGalleryLayout(12, 6), { cells: 12, moreIndex: -1, moreCount: 0 });
assert.deepEqual(getGalleryLayout(20, 6), { cells: 18, moreIndex: 17, moreCount: 3 });
assert.deepEqual(getGalleryLayout(30, 6), { cells: 24, moreIndex: 23, moreCount: 7 });

console.log('Kogo gallery layout test passed.');
