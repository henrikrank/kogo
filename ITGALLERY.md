# ITGallery sync

Use **ITGallery → Sync now** in the WordPress admin toolbar. Sync imports artists,
exhibitions and works into their existing custom post types.

With WPML enabled, sync fetches the catalogue for every enabled language. The
API language setting is the locale of the default WPML language (`en_EN` for
English). Estonian uses `et_EE`. Additional locale mappings can use the
`kogo_itgallery_api_locale` filter. The theme's `wpml-config.xml` makes the three
post types translatable with a default-language fallback and disables automatic
paid translation for them.

The same ITGallery ID identifies a record in each language. The connector reuses
post IDs and links language versions with WPML's native translation groups.
Artist/work/exhibition relationships point at posts in the same language. Artist
categories remain shared, including the homepage's Featured category.

Descriptions, biographies and artwork details are saved in `post_content` as
editable WordPress blocks. Open the imported post in the normal block editor;
each translation has its own content. Galleries, portraits and related work
lists use metadata stored by sync. Public pages do not call the ITGallery API.
Older unsynced artist/exhibition posts keep their existing template behavior.

Later syncs refresh unedited copy and metadata. Editing the saved blocks or title
protects that field from subsequent API changes. The first migration to blocks
also retains existing local text. Sync reports the number of changed records
whose local edits it preserved.

Every language request finishes before catalogue writes begin. A request failure
stops the sync. Imported records absent from a language's complete response move
to Trash only in that language, even when WPML's delete-all-translations option
is enabled. Returning records reuse their original IDs. The operation is not a
database transaction: a later WordPress save failure can leave earlier records
updated; rerun the sync to resume.

ITGallery may return the same source text for multiple API languages. The
connector preserves that response; it does not invent a translation. The
catalogue checked on 13 September 2026 contained localized fields for 265 of 360
works and two of 13 exhibitions. All 27 artist records had identical text in
English and Estonian. These copies can be translated manually in WordPress.

Integration check (local WordPress with WPML):

```sh
source '/Users/rank/Local Sites/kogo/app/.envrc'
wp --path='/Users/rank/Local Sites/kogo/app/public' --context=admin \
  --skip-plugins=woocommerce,woocommerce-multilingual \
  eval-file tests/kogo-itgallery-wpml-test.php
```

The check uses temporary post types and fixtures, leaving the real catalogue
alone. WooCommerce is omitted to avoid its unrelated CLI admin bootstrap error.
Deployment of the translated database uses `make deploy-with-db`.
