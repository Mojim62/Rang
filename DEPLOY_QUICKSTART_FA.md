# 🚀 استقرار سریع بامرو — مسیر Release → LIVE

> هدف: ۸۰٪ کار قبل از رسیدن به هاست انجام شده باشد. شما فقط: Upload → .env → Install → Health → LIVE

## بستهٔ استقرار (bamero-release.zip)

بستهٔ رسمی در GitHub Actions ساخته می‌شود (هر push به `main` و هر تگ `v*`):
**Actions → Build release package → آخرین اجرا → artifact `bamero-release`**

محتویات بسته — دقیقاً این و نه بیشتر:

```
bamero-release/
├── wp-config.php    # fail-closed، credentials از محیط
├── .htaccess        # قوانین امنیت/کش سرور
├── wp-content/      # تم + ۶ افزونهٔ بامرو
├── VERSION          # نسخهٔ build
└── SHA256SUMS       # کنترل یکپارچگی: sha256sum -c SHA256SUMS
```

نکته: سازندهٔ بسته یکی است — `scripts/build-release.sh` — که هم CI و هم توسعه‌دهندهٔ محلی اجرایش می‌کنند؛ یعنی خروجی لپ‌تاپ = خروجی CI.

## ۵ گام تا LIVE

| گام | کار | زمان |
|-----|-----|------|
| ۱ | هاست PHP **8.3** (نسخهٔ canonical) + SSL + دیتابیس MySQL | ۱۰ دقیقه |
| ۲ | دانلود `bamero-release.zip` از Actions و اکسترکت در `public_html` + `sha256sum -c SHA256SUMS` | ۵ دقیقه |
| ۳ | ساخت `.env` **بیرون از webroot** (مثلاً `/home/ACCOUNT/bamero.env`): اطلاعات دیتابیس، [۸ کلید salt](https://api.wordpress.org/secret-key/1.1/salt/)، `ZARINPAL_MERCHANT_ID`، `ZARINPAL_CURRENCY=IRR` | ۵ دقیقه |
| ۴ | `https://دامنه/wp-admin/install.php` — نام مدیر **admin نباشد** → فعال‌سازی WooCommerce + تم بامرو + ۶ افزونه | ۵ دقیقه |
| ۵ | **Health:** `wp eval-file tests/health_check.php` و **Seed (فقط اولین نصب):** `wp eval-file tests/seed_validation.php` | ۲ دقیقه |

> اگر هاست اجازهٔ فایل بیرون از webroot نمی‌دهد: `.env` داخل `public_html` هم توسط `.htaccess` بلاک می‌شود؛ اما مسیر بیرون از webroot ترجیح دارد.

## تفکیک تست‌ها (مهم)

- **`tests/health_check.php`** — سلامت محیط production: WordPress/DB/HTTPS/افزونه‌ها/درگاه/cron/uploads/صفحات حقوقی. **محیط‌آگنوستیک** — تعداد محصول یا دسته برایش مهم نیست.
- **`tests/seed_validation.php`** — فقط اعتبار دیتای اولیهٔ demo (۲۰ محصول، ۶ دسته، SKU، مقیاس ریالی). در سایت live اجرا نشود.
- `tests/staging_smoke.php` — اجرای هر دو، مخصوص staging اولیه.

## Rollback در ۵ دقیقه

```
releases/
├── 1.0.0/
├── 1.0.1/
└── 1.0.2/   ← current (symlink یا rename)
```

قبل از هر ارتقا: `cp -a public_html releases/$(cat public_html/VERSION)`.
اگر نسخهٔ جدید مشکل داشت: پوشهٔ قبلی را برگردانید و `.env` همان‌جا خارج از webroot سر جای خودش است — دیتابیس دست‌نخورده می‌ماند.

## قانون‌های این پروژه

- **یک build** (`scripts/build-release.sh`)، **یک artifact** (`bamero-release.zip`)
- **یک مسئول برای هر وظیفه**: یک راه‌حل کش (Cache Enabler یا معادل هاست)؛ نه پنج افزونهٔ بهینه‌سازی
- **PHP 8.3** نسخهٔ canonical هدف production — همان نسخه‌ای که CI و production gate روی آن lint/verify می‌شوند
- **نمی‌سازیم**: Docker/K8s/Redis اجباری/صف پیامیده/میکروسرویس — برای ~۱۰۰۰ کاربر، این‌ها debt پیشاپیش‌اند. Redis فقط اگر هاست آماده داشت (optimization، نه prerequisite)
- **HPOS**: فقط بعد از تأیید سازگاری افزونه‌های بامرو فعال شود
- Backup: روزانه DB + uploads، هفتگی کامل؛ **حداقل یک restore واقعی**
