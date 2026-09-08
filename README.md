# ChronoIran SEO Content API

WordPress plugin for drafting, reviewing, and publishing WooCommerce product-category and product SEO content through REST endpoints.

## Current version

`0.3.0`

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce
- Yoast SEO (recommended for SEO title, meta description, focus keyword, and canonical URL)

## Base URL

`https://chronoiran.com/wp-json/chrono-seo/v1`

## Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/health` | API/plugin dependency status |
| GET | `/categories` | List product categories |
| POST | `/categories` | Create or update a category draft by `external_id`/slug |
| GET | `/categories/{id}` | Read category, live SEO, and draft |
| PUT | `/categories/{id}` | Save category SEO draft |
| POST | `/categories/{id}/publish` | Publish approved category draft |
| GET | `/products` | List published products |
| GET | `/products/{id}` | Read product, live SEO, and draft |
| PUT | `/products/{id}` | Save product SEO draft |
| POST | `/products/{id}/publish` | Publish approved product draft |
| POST | `/preview` | Validate lengths, focus keyword, and Unicode/Persian word count |

List endpoints accept `page`, `per_page`, and `search`. Products additionally accept `category` and exact `sku`. Categories additionally accept `parent` and `status`.

## Draft and publish behavior

- `status: draft` or `pending` saves only plugin metadata. It does not change public product/category content.
- `POST .../publish` applies the draft to WordPress and Yoast metadata.
- `status: published` in a PUT/POST request saves and publishes in one explicit request.
- `external_id` is persisted immediately as a technical identifier so retries are idempotent.
- Existing 0.1.0 JSON-string drafts are read and migrated naturally on the next write.

## Example product draft

```json
{
  "external_id": "sheet-product-123",
  "seo_title": "عنوان سئو محصول",
  "meta_description": "توضیحات متای محصول",
  "focus_keyword": "کلمه کلیدی محصول",
  "description": "<p>محتوای کامل محصول</p>",
  "short_description": "توضیح کوتاه محصول",
  "status": "draft"
}
```

## Example category publish

The documented flat fields are the canonical request format:

```json
{
  "external_id": "black-mens-watch",
  "name": "ساعت مشکی مردانه",
  "slug": "black-mens-watch",
  "description": "<p>محتوای دسته‌بندی</p>",
  "seo_title": "خرید و قیمت ساعت مشکی (اورجینال + گارانتی)",
  "meta_description": "خرید آنلاین ساعت مشکی اصل با ضمانت اصالت کالا، گارانتی معتبر و بهترین قیمت.",
  "focus_keyword": "ساعت مشکی مردانه",
  "canonical_url": "",
  "status": "published"
}
```

For compatibility with existing automations, category writes also accept `title`
as an alias for `name`, `content` as an alias for `description`, and nested Yoast
fields:

```json
{
  "title": "ساعت مشکی مردانه",
  "slug": "black-mens-watch",
  "content": "<p>محتوای دسته‌بندی</p>",
  "yoast": {
    "title": "خرید و قیمت ساعت مشکی (اورجینال + گارانتی)",
    "description": "خرید آنلاین ساعت مشکی اصل با ضمانت اصالت کالا، گارانتی معتبر و بهترین قیمت.",
    "focus_keyword": "ساعت مشکی مردانه",
    "canonical_url": ""
  },
  "status": "published"
}
```

When both formats are present, the flat canonical field wins. Remember that
omitting `status: published` saves a draft only; alternatively call
`POST /categories/{id}/publish` after review.

## Upgrading from 0.2.0

Version 0.3.0 fixes taxonomy SEO storage by writing category fields through
Yoast's taxonomy API and rebuilding the corresponding Yoast indexable. Category
SEO values published by 0.2.0 were mirrored only to WordPress term meta, which
Yoast does not use for taxonomy output. Re-publish each affected category once
after updating the plugin to migrate its saved draft into Yoast's live taxonomy
metadata.

## Security note

Version 0.3.0 preserves the current public-access behavior for compatibility with the existing n8n workflow. Do not expose write/publish URLs in public workflows. The `chronoiran_seo_api_permission` WordPress filter is available for adding authentication in a later version without changing endpoint callbacks.
