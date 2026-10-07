# راهنمای Go-Live روی هاست PHP — فروشگاه بامرو

این سند گام‌به‌گام توضیح می‌دهد چگونه پروژهٔ بامرو را روی یک **هاست اشتراکی PHP** (cPanel / DirectAdmin / Plesk) راه‌اندازی کنید.

> **مهم:** تمام مقادیر حساس (اطلاعات پایگاه‌داده، کلید زرین‌پال، کلید SMS.ir و دامنه) در فایل `.env` قرار می‌گیرند و **در پایان توسط شما** تکمیل می‌شوند. هیچ مقدار حساسی در کد hard-code نشده است.

---

## ۰) پیش‌نیازها

| مورد | حداقل |
|------|-------|
| PHP | **حداقل 8.3** — `tests/health_check.php` به‌صورت fail-closed همین را اجباری می‌کند و CI نیز روی 8.3 اجرا می‌شود (8.4 برای production توصیه می‌شود) |
| افزونه‌های PHP | `mbstring`, `curl`, `openssl`, `json`, `gd`/`imagick` |
| پایگاه‌داده | **MySQL 8.0+ یا MariaDB 10.11+** (توصیهٔ رسمی) با charset `utf8mb4` |
| SSL | گواهی HTTPS فعال (Let's Encrypt) |
| دسترسی | FTP/SFTP یا File Manager پنل هاست |

---

## ۱) ساخت پایگاه‌داده

در پنل هاست (مثلاً cPanel > MySQL Databases):

1. یک پایگاه‌داده بسازید، مثلاً `bamero_wp`.
2. یک کاربر بسازید و به پایگاه‌داده با دسترسی کامل (ALL PRIVILEGES) وصل کنید.
3. این سه مقدار را یادداشت کنید: **نام پایگاه‌داده**، **نام کاربر**، **رمز عبور**.
4. Host معمولاً `localhost` است.

---

## ۲) آپلود فایل‌ها

۱. **هستهٔ WordPress (فارسی):** نسخهٔ `fa_IR` را از [wordpress.org/download](https://fa.wordpress.org/download/) دانلود و محتوای آن را در `public_html` (یا ریشهٔ دامنه) استخراج کنید.

۲. **فایل‌های بامرو:** پوشهٔ `wp-content` این پروژه را روی `wp-content` هاست بازنویسی (overwrite) کنید. ساختار نهایی باید این‌گونه باشد:

```
public_html/
├── wp-admin/            (از هستهٔ WordPress)
├── wp-includes/         (از هستهٔ WordPress)
├── wp-content/
│   ├── themes/bamero/   ← از این پروژه
│   └── plugins/bamero-* ← از این پروژه
├── wp-config.php        ← از این پروژه
├── .htaccess            ← از این پروژه
└── index.php            (از هستهٔ WordPress)
```

۳. فایل‌های `wp-config.php` و `.htaccess` این پروژه را در ریشهٔ سایت قرار دهید (نسخهٔ پیش‌فرض WordPress را جایگزین کنید).

> ⚠️ فایل `wp-config.php` این پروژه **fail-closed** است: اگر `.env` ناقص باشد، سایت با خطای ۵۰۰ بالا نمی‌آید. این عمدی است تا راه‌اندازی ناامن رخ ندهد.

---

## ۳) پیکربندی `.env` (جای‌گذاری کلیدها و دامنه)

۱. فایل `.env.example` را به `.env` کپی کنید:

```bash
cp .env.example .env
```

۲. فایل `.env` را باز کنید و **همهٔ** مقادیر را پر کنید:

```ini
# --- پایگاه‌داده ---
DB_NAME=bamero_wp
DB_USER=bamero_user
DB_PASSWORD=رمز_عبور_قوی
DB_HOST=localhost
TABLE_PREFIX=wp_bamero_

# --- دامنه (اختیاری؛ برای پین کردن آدرس سایت) ---
WP_HOME=https://rang.example.ir
WP_SITEURL=https://rang.example.ir

# --- کلیدهای امنیتی (۸ مقدار یکتا) ---
# از https://api.wordpress.org/secret-key/1.1/salt/ بسازید
AUTH_KEY=...
SECURE_AUTH_KEY=...
LOGGED_IN_KEY=...
NONCE_KEY=...
AUTH_SALT=...
SECURE_AUTH_SALT=...
LOGGED_IN_SALT=...
NONCE_SALT=...

# --- پرچم‌های اجرا ---
WP_ENVIRONMENT_TYPE=production
WP_DEBUG=0
WP_DEBUG_LOG=0
DISALLOW_FILE_MODS=1

# --- شناسهٔ داخلی مشتری ---
BAMERO_INTERNAL_ID_SALT=یک_رشته_تصادفی_بلند

# --- SMS.ir ---
SMS_PROVIDER=sms_ir
SMS_TIMEOUT=5
SMS_IR_API_BASE_URL=https://api.sms.ir/v1
SMS_IR_API_KEY=کلید_API_شما
SMS_IR_OTP_PARAMETER=Code
SMS_IR_ORDER_PARAMETER=OrderId
SMS_IR_TEMPLATE_LOGIN_OTP=شناسه_قالب_ورود
SMS_IR_TEMPLATE_ORDER_PROCESSING=شناسه_قالب_پردازش
SMS_IR_TEMPLATE_ORDER_DELIVERED=شناسه_قالب_تحویل
SMS_IR_TEMPLATE_ORDER_CANCELLED=شناسه_قالب_لغو
SMS_IR_TEMPLATE_PAYMENT_FAILED=شناسه_قالب_خطای_پرداخت
SMS_IR_TEMPLATE_REFUND_COMPLETED=شناسه_قالب_بازگشت_وجه

# --- زرین‌پال ---
ZARINPAL_API_BASE_URL=https://payment.zarinpal.com/pg/v4
ZARINPAL_STARTPAY_URL=https://payment.zarinpal.com/pg/StartPay
ZARINPAL_MERCHANT_ID=مرچنت_کد_زرین‌پال
ZARINPAL_CURRENCY=IRR
BAMERO_PAYMENT_WEBHOOK_SECRET=یک_رشته_تصادفی_بلند
```

۳. **محل قرارگیری `.env`** (به ترتیب اولویت، `wp-config.php` خودکار پیدا می‌کند):

   - **توصیه‌شده:** یک پوشه بالاتر از web root، با نام `bamero.env` یا `.env`
     (مثلاً اگر ریشهٔ سایت `public_html` است، فایل را در `home/username/.env` بگذارید).
   - **جایگزین:** داخل ریشهٔ سایت با نام `.env` — این مسیر توسط `.htaccess` از دسترس HTTP مسدود است.

> 🔐 **هرگز** فایل `.env` را در Git کامیت نکنید. فایل `.gitignore` از قبل آن را نادیده می‌گیرد.

---

## ۴) نصب WordPress

۱. به `https://YOUR-DOMAIN/wp-admin/install.php` بروید.
۲. چون `wp-config.php` این پروژه اطلاعات پایگاه‌داده را از `.env` می‌خواند، مرحلهٔ پیکربندی پایگاه‌داده رد می‌شود؛ فقط **عنوان سایت، نام کاربری مدیر و ایمیل مدیر** را وارد کنید.
۳. اگر `WP_HOME`/`WP_SITEURL` را در `.env` تنظیم کرده‌اید، آدرس سایت خودکار پین می‌شود.

---

## ۵) فعال‌سازی تم و افزونه‌ها

از پیشخوان WordPress > **نمایش > پوسته‌ها**:

- تم **بامرو** را فعال کنید.

از **افزونه‌ها**:

- `bamero-production-core` (هستهٔ امنیت و پیامک)
- `bamero-mobile-auth` (ورود با موبایل)
- `bamero-zarinpal-gateway` (درگاه پرداخت)
- `bamero-woocommerce-setup` (دسته‌ها و محصولات نمونه)
- `bamero-custom-plugin` (صفحات محتوا)
- `bamero-essential-plugins` (نصب‌کنندهٔ افزونه‌های پیشنهادی)

سپس **WooCommerce** و در صورت تمایل افزونه‌های پیشنهادی (YITH Wishlist، Autoptimize، WP Super Cache، Rank Math، Wordfence) را نصب و فعال کنید.

> **نسخهٔ ووکامرس و قالب‌های بازنویسی‌شده:** قالب‌های بازنویسی‌شدهٔ `cart.php` و `form-checkout.php` از ووکامرس ۹.۰ گرفته شده‌اند و با سری ۱۰ و ۱۱ نیز کار می‌کنند، اما پس از هر ارتقای major ووکامرس، بخش **WooCommerce > Status** را برای هشدار «outdated template» بازبینی و سبد خرید/پرداخت را یک‌بار دود-تست کنید. اگر WP Super Cache نصب و فعال کردید، مقدار `WP_CACHE` را در `.env` به `1` تغییر دهید.

> اگر `DISALLOW_FILE_MODS=1` باشد، نصب افزونه از پنل غیرفعال است؛ افزونه‌ها را دستی در `wp-content/plugins` آپلود کنید یا موقتاً این پرچم را `0` کنید و پس از نصب دوباره `1` کنید.

---

## ۶) پیکربندی نهایی

| مورد | مسیر | مقدار |
|------|------|-------|
| Permalinks | تنظیمات > پیوندهای یکتا | **Post name** |
| Timezone | تنظیمات > همگانی | **تهران (UTC+3:30)** |
| Currency | WooCommerce > تنظیمات | **ریال (IRR)** |
| Country | WooCommerce > تنظیمات | **Iran (IR)** |
| اطلاعات تماس | نمایش > سفارشی‌سازی > اطلاعات تماس بامرو | شماره، نشانی، شبکه‌های اجتماعی |
| درگاه زرین‌پال | WooCommerce > تنظیمات > پرداخت | فعال‌سازی «زرین‌پال بامرو» |
| روش‌های ارسال | WooCommerce > تنظیمات > حمل‌ونقل | پست/تیپاکس/تحویل در محل |

---

## ۷) تست پرداخت و پیامک

۱. یک محصول ارزان به سبد اضافه کنید و به تسویه بروید.
۲. روش پرداخت زرین‌پال را انتخاب و پرداخت را در محیط **تست** زرین‌پال کامل کنید.
۳. پس از بازگشت از درگاه، وضعیت سفارش باید به «پرداخت‌شده» تغییر کند (فقط پس از verify موفق).
۴. یک ورود با شمارهٔ موبایل را تست کنید و کد OTP را دریافت کنید.
۵. در صورت بروز خطا، لاگ‌ها را در `wp-content/debug.log` (در صورت فعال بودن) بررسی کنید.

---

## ۷ب) دود-تست استیجینگ (پیش از go-live الزامی)

پس از فعال‌سازی همهٔ افزونه‌ها روی استیجینگ، این تست را اجرا کنید تا سلامت محیط اثبات شود:

```bash
wp eval-file tests/staging_smoke.php
```

این اسکریپت به‌صورت خودکار بررسی می‌کند: ارز **ریال (IRR)**، ۲۰ محصول منتشرشده با SKU یکتای `RP-XX-NNN`، ۶ دسته، مقیاس صحیح قیمت‌های ریالی، فعال بودن درگاه زرین‌پال و `ZARINPAL_MERCHANT_ID`، فعال بودن production-core، وجود جدول اعلان‌های outbox در پایگاه‌داده (`wp_bamero_queue_notification` که در فعال‌سازی افزونه با `dbDelta` ساخته می‌شود)، نبودن کاربر `admin`، غیرفعال بودن عضویت آزاد و وجود صفحات «حریم خصوصی» و «شرایط استفاده» (این دو صفحه با فعال‌سازی افزونهٔ بامرو سفارشی به‌صورت خودکار ساخته می‌شوند). در صورت شکست هر بررسی، اسکریپت با کد غیرصفر خارج می‌شود و go-live ممنوع است.

ساخت بستهٔ استقرار تمیز (بدون docs/tests/.git):

```bash
bash scripts/build-release.sh   # خروجی: dist/bamero-release.zip
```

---

## ۸) چک‌لیست امنیت و بهینه‌سازی پس از نصب

- [ ] HTTPS فعال و ریدایرکت اجباری (`FORCE_SSL_ADMIN=true` از قبل تنظیم است).
- [ ] فایل `.env` بیرون از web root یا مسدود با `.htaccess`.
- [ ] `WP_DEBUG=0` و `DISALLOW_FILE_MODS=1`.
- [ ] رمز عبور پایگاه‌داده قوی و کاربر با حداقل دسترسی لازم.
- [ ] پشتیبان‌گیری خودکار (فایل + پایگاه‌داده) فعال.
- [ ] افزونهٔ امنیتی (Wordfence) و کش (WP Super Cache) فعال.
- [ ] `robots.txt` پویا و `sitemap` در Google Search Console ثبت شود.
- [ ] تست Core Web Vitals (PageSpeed / GTmetrix).

---

## ۹) رفع اشکال

| نشانه | علت احتمالی | راه‌حل |
|-------|-------------|--------|
| خطای ۵۰۰: `missing required environment variable` | `.env` پیدا نشد یا مقدار خالی است | فایل `.env` را در مسیر درست قرار دهید و همهٔ کلیدها را پر کنید |
| خطای ۵۰۰: `rejected placeholder/insecure value` | مقدار placeholder (مثل `changeme`) باقی مانده | مقدار واقعی را جای‌گذاری کنید |
| درگاه پرداخت کار نمی‌کند | `ZARINPAL_MERCHANT_ID` خالی/نادرست | مرچنت کد را در `.env` بررسی کنید |
| کد OTP نمی‌آید | `SMS_IR_API_KEY` یا template ID نادرست | کلید و شناسهٔ قالب‌ها را بررسی کنید |
| دامنه اشتباه در لینک‌ها | `WP_HOME`/`WP_SITEURL` تنظیم نشده | این دو مقدار را در `.env` تنظیم کنید |

---

## ۱۰) فایل‌هایی که نباید روی هاست عمومی قرار گیرند

پوشه‌های `docs/` و `tests/` صرفاً برای توسعه و ممیزی هستند و **نباید** در web root آپلود شوند (یا حداقل با `.htaccess` مسدود شوند که از قبل انجام شده). فرمان `zip` در پیوست (بستهٔ استقرار) فقط فایل‌های لازم برای اجرا را شامل می‌شود و این پوشه‌ها را در بر نمی‌گیرد؛ آرتیفکت CI صرفاً اسنپ‌شات کامل سورس برای ممیزی است.

---

## پیوست: دستورات مفید

```bash
# بررسی سینتکس PHP همهٔ فایل‌ها (نیازمند PHP CLI)
bash tests/production_gate.sh

# ساخت آرشیو استقرار تمیز (بدون docs/tests)
zip -r bamero-deploy.zip wp-content wp-config.php .htaccess .env.example
```
