# رابط محتوای سئوی کورنو ایران

افزونه وردپرس برای ساخت، ذخیره پیش‌نویس، بررسی و انتشار محتوای سئو دسته‌های ووکامرس و محصولات از طریق REST API.

**طراحی و توسعه: مهدی توکلی**

## نسخه فعلی

`0.4.2`

## نیازمندی‌ها

- WordPress 6.0 یا جدیدتر
- PHP 7.4 یا جدیدتر
- WooCommerce فعال
- Yoast SEO برای انتشار عنوان سئو، توضیحات متا، کلمه کلیدی و canonical

## آدرس پایه

```text
https://chronoiran.com/wp-json/chrono-seo/v1
```

## راه‌اندازی سریع برای همکار

### 1. ابتدا نسخه نصب‌شده را بررسی کنید

```http
GET /health
```

مقدار `version` باید `0.4.2` باشد و `woocommerce`، `product_cat` و `yoast` بهتر است `true` باشند. همچنین `html_publish_fix` و `yoast_frontend_fallback` باید `true` باشند.

### 2. هنگام ارسال دسته حتماً وضعیت را انتخاب کنید

یکی از این دو مقدار را در همان درخواست بفرستید:

```json
"status": "draft"
```

یا:

```json
"status": "published"
```

معادل بولی آن نیز پشتیبانی می‌شود: `publish_now: false` برای پیش‌نویس و `publish_now: true` برای انتشار فوری. اگر نه `status` و نه `publish_now` ارسال شود، API عمداً خطای `400 missing_publish_choice` می‌دهد. بهتر است فقط از `status` استفاده کنید.

### 3. نمونه کامل ایجاد و انتشار فوری دسته

این قالب دقیقاً با خروجی محتوای فعلی تیم سازگار است:

```json
{
  "title": "ساعت مشکی مردانه",
  "h1": "ساعت مشکی مردانه",
  "seo_title": "خرید و قیمت ساعت مشکی (اورجینال+ گارانتی)",
  "seo_description": "خرید آنلاین ساعت مشکی اصل با ضمانت اصالت کالا و گارانتی معتبر همراه با بهترین قیمت و فروش رسمی.",
  "url": "black-mens-watch",
  "category_url": "",
  "description": "<h2 class=\"section-title\">راهنمای خرید ساعت مشکی مردانه</h2><p>محتوای کامل دسته‌بندی در این قسمت قرار می‌گیرد.</p>",
  "focus_keyword": "ساعت مشکی مردانه",
  "canonical_url": "",
  "external_id": "black-mens-watch",
  "status": "published"
}
```

درخواست:

```http
POST /categories
Content-Type: application/json
```

`POST /categories` یک عملیات upsert است: ابتدا با `external_id` و سپس با `slug` جستجو می‌کند؛ دسته موجود را به‌روزرسانی و در غیر این صورت دسته جدید ایجاد می‌کند.

### 4. نمونه ذخیره به‌صورت پیش‌نویس

همان JSON بالا را با این مقدار ارسال کنید:

```json
"status": "draft"
```

در حالت draft، محتوای جدید و فیلدهای Yoast روی نسخه زنده اعمال نمی‌شوند. چون taxonomy وردپرس وضعیت پیش‌نویس واقعی ندارد، رکورد دسته با نام و slug ساخته می‌شود تا ID داشته باشد؛ اما محتوای ارسالی در متای پیش‌نویس افزونه نگهداری می‌شود.

### 5. انتشار پیش‌نویس ذخیره‌شده

ID را از پاسخ مرحله قبل بردارید و درخواست زیر را بفرستید:

```http
POST /categories/{id}/publish
```

برای این endpoint نیازی به ارسال دوباره محتوا یا `status` نیست.

## وضعیت‌ها

| مقدار | نتیجه |
| --- | --- |
| `draft` | تغییرات فقط در پیش‌نویس افزونه ذخیره می‌شوند. |
| `pending` | مانند draft ذخیره می‌شود و برای گردش کار بررسی قابل استفاده است. |
| `published` | محتوا، نام، slug، والد و تنظیمات Yoast همان لحظه اعمال می‌شوند. |
| `publish_now: false` | معادل `status: draft` است. |
| `publish_now: true` | معادل `status: published` است. |

اگر `status` و `publish_now` را هم‌زمان با مقادیر متناقض بفرستید، پاسخ `400 conflicting_publish_choice` دریافت می‌کنید.

## فیلدهای دسته‌بندی

| فیلد پیشنهادی | نوع | توضیح |
| --- | --- | --- |
| `external_id` | string | شناسه ثابت سیستم بیرونی؛ برای جلوگیری از دسته تکراری توصیه می‌شود. |
| `name` | string | نام دسته؛ هنگام ساخت دسته جدید الزامی است. |
| `title` | string | نام جایگزین برای `name`. |
| `h1` | string | نام جایگزین برای `name`؛ در نبود `name` اولویت آن از `title` بیشتر است. |
| `slug` | string | اسلاگ انگلیسی دسته. |
| `url` | string | جایگزین `slug`؛ اسلاگ یا URL کامل را می‌پذیرد. |
| `parent` | integer | ID دسته والد؛ صفر یعنی بدون والد. |
| `category_url` | string | URL یا slug دسته والد؛ به ID والد تبدیل می‌شود. مقدار خالی مجاز است. |
| `description` | HTML string | محتوای استاندارد و قابل‌نمایش دسته ووکامرس. |
| `content` | HTML string | جایگزین `description`. |
| `seo_title` | string | عنوان سئو Yoast. |
| `meta_description` | string | توضیحات متا Yoast. |
| `seo_description` | string | جایگزین `meta_description` و سازگار با خروجی فعلی تیم. |
| `focus_keyword` | string | کلمه کلیدی کانونی Yoast. |
| `canonical_url` | string | URL کامل canonical یا رشته خالی برای حالت پیش‌فرض. |
| `status` | enum | یکی از `draft`، `pending` یا `published`. |
| `publish_now` | boolean | انتخاب جایگزین برای انتشار فوری. |

اگر فیلد اصلی و نام جایگزین هر دو ارسال شوند، فیلد اصلی اولویت دارد؛ مثلاً `meta_description` بر `seo_description` مقدم است.

## قالب تو‌در‌توی Yoast

علاوه بر فیلدهای تخت، این قالب نیز پشتیبانی می‌شود:

```json
{
  "name": "ساعت مشکی مردانه",
  "slug": "black-mens-watch",
  "description": "<p>محتوای دسته‌بندی</p>",
  "yoast": {
    "title": "خرید و قیمت ساعت مشکی (اورجینال+ گارانتی)",
    "description": "توضیحات متای صفحه",
    "focus_keyword": "ساعت مشکی مردانه",
    "canonical_url": ""
  },
  "status": "published"
}
```

در صورت وجود هر دو قالب، فیلدهای تخت مانند `seo_title` و `meta_description` اولویت دارند.

## پیدا کردن ID دسته

```http
GET /categories?slug=black-mens-watch
GET /categories?external_id=black-mens-watch
GET /categories?search=ساعت مشکی
GET /categories?status=draft
GET /categories?status=published
```

صفحه‌بندی با `page` و `per_page` انجام می‌شود. هدرهای `X-WP-Total`، `X-WP-TotalPages`، `X-WP-Page` و `X-WP-Per-Page` نیز برگردانده می‌شوند.

## بررسی پاسخ موفق

فقط دریافت HTTP 200 یا 201 کافی نیست. این بخش‌ها را بررسی کنید:

```json
{
  "id": 123,
  "slug": "black-mens-watch",
  "url": "https://chronoiran.com/category/black-mens-watch",
  "status": "published",
  "workflow": {
    "selected_status": "published",
    "has_saved_draft": true,
    "draft_applied_to_live": true,
    "term_record_exists": true,
    "taxonomy_supports_draft": false,
    "message": "محتوا و تنظیمات سئو روی نسخه زنده اعمال شده‌اند."
  },
  "verification": {
    "url_resolved": true,
    "yoast_active": true,
    "description_matches_draft": true,
    "yoast_title_matches_draft": true,
    "yoast_desc_matches_draft": true
  },
  "warnings": []
}
```

در انتشار موفق، `status` باید `published`، مقدار `workflow.draft_applied_to_live` باید `true` و بررسی‌های `verification` باید `true` باشند. آرایه `warnings` را همیشه بررسی کنید.

## نکته مهم درباره نمایش محتوا

برای محتوایی که باید در صفحه دسته ووکامرس نمایش داده شود، از `description` استفاده کنید. فیلدهای `intro_content` و `outro_content` متای سفارشی هستند و فقط در صورت پشتیبانی قالب نمایش داده می‌شوند. اگر API داده صحیح برمی‌گرداند ولی متن صفحه دیده نمی‌شود، نمایش توضیحات دسته در قالب ووکامرس/Woodmart باید بررسی شود.

## endpointها

| متد | endpoint | کاربرد |
| --- | --- | --- |
| GET | `/health` | نسخه، وابستگی‌ها و حالت احراز هویت |
| GET | `/categories` | فهرست و جستجوی دسته‌ها |
| POST | `/categories` | ساخت یا به‌روزرسانی دسته با انتخاب وضعیت |
| GET | `/categories/{id}` | مشاهده داده زنده، پیش‌نویس، Yoast و بررسی نتیجه |
| PUT/PATCH | `/categories/{id}` | ذخیره تغییر دسته با انتخاب وضعیت |
| POST | `/categories/{id}/publish` | انتشار پیش‌نویس ذخیره‌شده |
| GET | `/products` | فهرست محصولات منتشرشده |
| GET | `/products/{id}` | مشاهده محصول، پیش‌نویس و Yoast |
| PUT/PATCH | `/products/{id}` | ذخیره یا انتشار محتوای محصول |
| POST | `/products/{id}/publish` | انتشار پیش‌نویس محصول |
| POST | `/preview` | بررسی طول عنوان، متا، کلمه کلیدی و تعداد کلمات |

## نمونه cURL انتشار دسته

```bash
curl --request POST \
  'https://chronoiran.com/wp-json/chrono-seo/v1/categories' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "external_id": "black-mens-watch",
    "name": "ساعت مشکی مردانه",
    "slug": "black-mens-watch",
    "description": "<p>محتوای کامل دسته‌بندی</p>",
    "seo_title": "خرید و قیمت ساعت مشکی (اورجینال+ گارانتی)",
    "meta_description": "خرید آنلاین ساعت مشکی اصل با ضمانت اصالت کالا و گارانتی معتبر.",
    "focus_keyword": "ساعت مشکی مردانه",
    "canonical_url": "",
    "status": "published"
  }'
```

اگر کلید API فعال است، هدر `X-Chrono-API-Key: YOUR_SECRET_KEY` را نیز اضافه کنید.

## تنظیم n8n

- Method: `POST` برای `/categories` یا `PUT` برای `/categories/{id}`
- Send Body: روشن
- Body Content Type: `JSON`
- Header: `Content-Type: application/json`
- فیلد `status`: حتماً `draft` یا `published`
- Never Error: خاموش باشد تا خطاهای 4xx/5xx نادیده گرفته نشوند.
- Full Response: روشن باشد تا HTTP status و هدر `X-Chrono-Status` قابل بررسی باشند.
- بعد از درخواست، `status`، `workflow`، `verification` و `warnings` بررسی شوند.

## API محصول

محصول جدید توسط این افزونه ساخته نمی‌شود؛ فقط محصولات موجود به‌روزرسانی می‌شوند:

```json
{
  "title": "عنوان محصول",
  "description": "<p>توضیحات کامل محصول</p>",
  "short_description": "توضیح کوتاه محصول",
  "seo_title": "عنوان سئو محصول",
  "seo_description": "توضیحات متای محصول",
  "focus_keyword": "کلمه کلیدی محصول",
  "canonical_url": "",
  "status": "draft"
}
```

برای انتشار فوری، `status` را به `published` تغییر دهید. قالب تو‌در‌توی `yoast` و نام جایگزین `content` برای محصولات نیز پشتیبانی می‌شوند.

## احراز هویت پیشنهادی

در `wp-config.php` یک کلید طولانی و تصادفی تعریف کنید:

```php
define( 'CHRONOIRAN_SEO_API_KEY', 'یک-کلید-طولانی-و-تصادفی' );
```

سپس در n8n یکی از هدرهای زیر را ارسال کنید:

```http
X-Chrono-API-Key: YOUR_SECRET_KEY
Authorization: Bearer YOUR_SECRET_KEY
```

هدر `X-Chrono-API-Key` برای سازگاری بیشتر با هاست پیشنهاد می‌شود. اگر ثابت بالا تعریف نشده باشد، رفتار عمومی نسخه‌های قبلی حفظ می‌شود و دسترسی را می‌توان با فیلتر `chronoiran_seo_api_permission` کنترل کرد.

## خطاهای مهم

| کد خطا | HTTP | علت و راه‌حل |
| --- | --- | --- |
| `missing_publish_choice` | 400 | `status` یا `publish_now` ارسال نشده است. |
| `conflicting_publish_choice` | 400 | دو انتخاب انتشار با هم تناقض دارند. |
| `empty_write_payload` | 400 | فقط وضعیت ارسال شده و هیچ فیلد محتوایی وجود ندارد؛ برای پیش‌نویس موجود از `/publish` استفاده کنید. |
| `missing_name` | 400 | هنگام ساخت، نام، title یا h1 ارسال نشده است. |
| `invalid_slug` | 400 | slug پس از پاک‌سازی خالی یا نامعتبر است. |
| `invalid_canonical_url` | 400 | canonical باید خالی یا URL کامل HTTP/HTTPS باشد. |
| `parent_category_not_found` | 400 | دسته والد موجود در `category_url` پیدا نشده است. |
| `category_not_found` | 404 | ID دسته محصول معتبر نیست. |
| `product_not_found` | 404 | ID محصول معتبر نیست. |
| `empty_category_draft` | 409 | برای این دسته پیش‌نویسی وجود ندارد. |
| `empty_product_draft` | 409 | برای این محصول پیش‌نویسی وجود ندارد. |
| `chrono_api_unauthorized` | 401 | کلید API ارسال نشده یا اشتباه است. |
| `woocommerce_unavailable` | 503 | ووکامرس یا taxonomy محصول در دسترس نیست. |

## مهاجرت از نسخه‌های قبلی

- فایل نسخه `0.4.2` را جایگزین نسخه قبلی و کش سایت را پاک کنید.
- `GET /health` را اجرا کنید و نسخه را بررسی کنید.
- دسته‌های منتشرشده با نسخه `0.2.0` را یک بار دوباره با `status: published` ارسال یا از endpoint انتشار منتشر کنید؛ آن نسخه متای taxonomy مربوط به Yoast را در محل اشتباه ذخیره می‌کرد.
- نسخه 0.4.0 قالب قدیمی فیلدهای تخت، قالب تو‌در‌توی Yoast و قرارداد خروجی فعلی تیم را هم‌زمان پشتیبانی می‌کند.

## چک‌لیست رفع اشکال

1. پاسخ `/health` باید نسخه `0.4.2` را نشان دهد.
2. HTTP status درخواست را بررسی کنید.
3. دسته را با `GET /categories?slug=...` پیدا کنید.
4. مطمئن شوید `status: published` ارسال شده است.
5. `live_description` باید محتوای منتشرشده را نشان دهد.
6. بخش `yoast` باید عنوان و توضیحات زنده را نشان دهد.
7. `verification` و `warnings` را بررسی کنید.
8. اگر داده API صحیح است ولی متن دیده نمی‌شود، تنظیم قالب ووکامرس/Woodmart را بررسی کنید.
9. اگر صفحه 404 است، پیوندهای یکتا را یک بار ذخیره و کش سایت را پاک کنید.

## امنیت

URLهای نوشتن و انتشار را در گردش‌کار عمومی قرار ندهید. فعال‌کردن `CHRONOIRAN_SEO_API_KEY` در محیط اصلی پیشنهاد می‌شود. فیلتر `chronoiran_seo_api_read_permission` نیز برای کنترل endpointهای خواندنی در دسترس است.

## کنترل نتیجه انتشار در نسخه ۰.۴.۲

پس از انتشار، مقادیر زیر را در بخش `verification` پاسخ بررسی کنید:

- `description_matches_draft`: ساختار HTML ذخیره‌شده با درخواست برابر است.
- `yoast_title_matches_draft`: عنوان Yoast درست ذخیره شده است.
- `yoast_desc_matches_draft`: توضیحات متای Yoast درست ذخیره شده است.
- `yoast_indexable_rebuilt`: بازسازی Indexable داخلی Yoast اجرا شده است.
- `all_requested_fields_match`: تمام فیلدهای درخواستی با نسخه زنده برابر هستند.

نسخه ۰.۴.۲ علاوه بر اصلاح بازسازی Indexable، یک مسیر جایگزین برای فیلترهای خروجی زنده Yoast دارد تا عنوان سئو، توضیحات متا، Open Graph، Twitter و canonical حتی در صورت خرابی کش داخلی Yoast از مقادیر ذخیره‌شده خوانده شوند. HTML توضیحات دسته نیز ابتدا با فهرست امن وردپرس پالایش و سپس بدون حذف ساختارهایی مانند تیتر، پاراگراف، فهرست و لینک ذخیره می‌شود.
