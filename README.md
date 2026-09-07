# ChronoIran SEO Content API

WordPress plugin for drafting, reviewing, and publishing WooCommerce product-category and product SEO content through REST endpoints.

## Current version

`0.2.0`

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

## Security note

Version 0.2.0 preserves the current public-access behavior for compatibility with the existing n8n workflow. Do not expose write/publish URLs in public workflows. The `chronoiran_seo_api_permission` WordPress filter is available for adding authentication in a later version without changing endpoint callbacks.

