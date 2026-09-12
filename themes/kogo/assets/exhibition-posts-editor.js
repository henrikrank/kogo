(function (wp) {
	'use strict';

	const el = wp.element.createElement;
	const { __, sprintf } = wp.i18n;
	const metaKey = 'kogo_linked_post_ids';
	const statuses = ['publish', 'future', 'draft', 'pending', 'private'];
	const statusLabels = { future: __('Scheduled', 'kogo'), draft: __('Draft', 'kogo'), pending: __('Pending review', 'kogo'), private: __('Private', 'kogo') };
	const postTitle = (post) => post.title.raw || __('(No title)', 'kogo');
	const postLabel = (post) => post.status === 'publish' ? postTitle(post) : `${postTitle(post)} (${statusLabels[post.status]})`;

	function LinkedPost({ id, onRemove }) {
		const post = wp.data.useSelect((select) => select('core').getEntityRecord('postType', 'post', id, { context: 'edit' }), [id]);
		const title = post ? postLabel(post) : sprintf(__('Post #%d', 'kogo'), id);
		return el('li', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' } },
			el('span', { style: { flex: 1, overflowWrap: 'anywhere' } }, title),
			el(wp.components.Button, { variant: 'tertiary', size: 'small', onClick: () => onRemove(id), label: sprintf(__('Unlink %s', 'kogo'), title) }, __('Remove', 'kogo'))
		);
	}

	function ExhibitionPostsPanel() {
		const [search, setSearch] = wp.element.useState('');
		const filterPosts = wp.compose.useDebounce(setSearch, 250);
		const ids = wp.data.useSelect((select) => select('core/editor').getEditedPostAttribute('meta')?.[metaKey] || [], []);
		const results = wp.data.useSelect((select) => select('core').getEntityRecords('postType', 'post', {
			search, per_page: 20, status: statuses, context: 'edit', orderby: 'date', order: 'desc',
		}), [search]);
		const { editPost } = wp.data.useDispatch('core/editor');
		const updateLinks = (nextIds) => editPost({ meta: { [metaKey]: nextIds } });
		const options = (results || []).filter((post) => !ids.includes(post.id)).map((post) => ({ value: String(post.id), label: postLabel(post) }));

		return el(wp.editor.PluginDocumentSettingPanel, { name: 'linked-posts', title: __('Linked posts', 'kogo') },
			el('p', null, __('Choose posts about this exhibition. Published posts appear in Events before Works, newest first.', 'kogo')),
			el(wp.components.ComboboxControl, {
				label: __('Search posts to link', 'kogo'),
				value: null,
				options,
				onFilterValueChange: filterPosts,
				onChange: (value) => {
					if (value && !ids.includes(Number(value))) {
						updateLinks([...ids, Number(value)]);
						setSearch('');
					}
				},
				help: !results ? __('Loading posts…', 'kogo') : !options.length ? __('No matching posts. Try another search.', 'kogo') : undefined,
				__next40pxDefaultSize: true,
				__nextHasNoMarginBottom: true,
			}),
			ids.length ? el('ul', { 'aria-label': __('Linked posts', 'kogo'), style: { margin: '16px 0 0', padding: 0, listStyle: 'none' } },
				ids.map((id) => el(LinkedPost, { key: id, id, onRemove: (removedId) => updateLinks(ids.filter((item) => item !== removedId)) }))
			) : el('p', null, __('No posts linked yet.', 'kogo'))
		);
	}

	wp.plugins.registerPlugin('kogo-exhibition-posts', { render: ExhibitionPostsPanel });
})(window.wp);
