import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../themes/kogo/assets/main.js', import.meta.url), 'utf8');
const setup = source.slice(source.indexOf('\tconst setupHeaderNavigation = () => {'), source.indexOf('\n\tconst setupSiteSearch ='));

const getActiveLinks = (url) => {
	const links = ['/', '/artists/', '/exhibitions/', '/news/', '/artist/', '#', '/artists/#biography', '/artists/?view=all', 'https://example.com/artists/'].map((href) => ({
		href,
		attributes: {},
		getAttribute(name) { return name === 'href' ? this.href : this.attributes[name]; },
		setAttribute(name, value) { this.attributes[name] = value; },
	}));
	runInNewContext(`${setup}\nsetupHeaderNavigation();`, {
		URL,
		window: { location: { href: url } },
		document: { querySelectorAll: () => links },
	});
	return links.filter((link) => link.attributes['aria-current']).map((link) => [link.href, link.attributes['aria-current']]);
};

assert.deepEqual(getActiveLinks('http://kogo.local/artists/'), [['/artists/', 'page']]);
assert.deepEqual(getActiveLinks('http://kogo.local/artists'), [['/artists/', 'page']]);
assert.deepEqual(getActiveLinks('http://kogo.local/artists/kristina-ollek/'), [['/artists/', 'location']]);
assert.deepEqual(getActiveLinks('http://kogo.local/exhibitions/floral-atlas/'), [['/exhibitions/', 'location']]);
assert.deepEqual(getActiveLinks('http://kogo.local/news/?page=2'), [['/news/', 'page']]);
assert.deepEqual(getActiveLinks('http://kogo.local/'), [['/', 'page']]);
assert.deepEqual(getActiveLinks('http://kogo.local/artists-extra/'), []);
assert.deepEqual(getActiveLinks('http://kogo.local/unknown/'), []);

console.log('Kogo header navigation test passed.');
