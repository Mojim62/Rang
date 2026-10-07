# 🔁 Rollback بامرو — بر پایهٔ Release قبلی (نه rebuild)

> اصل: هرگز برای rollback نسخهٔ قبلی را از source دوباره build نکنید.
> همیشه **همان artifact قبلی** (zip اثبات‌شده) را دوباره مستقر کنید.

## ۱) مصالح rollback (همیشه موجود)

- **Artifact نسخه‌ها**: هر push به `main` و هر tag `v*` در GitHub Actions با workflow
  `build-release.yml` بستهٔ `dist/bamero-release.zip` را می‌سازد و در
  **Actions → Artifacts** آپلود می‌کند (retention 30 روز؛ برای نگه‌داری بلندمدت tag بزنید).
- داخل هر artifact:
  - `VERSION` — خروجی `git describe` → **commit سازندهٔ دقیق artifact قابل ردیابی است**.
  - `SHA256SUMS` — چک‌سام تمام فایل‌ها؛ قبل از هر استقرار با `sha256sum -c SHA256SUMS` verify کنید.

## ۲) procedure استاندارد deployment (با نگاه rollback)

1. **known-good فعلی** را ثبت کنید: شمارهٔ Actions run / tag نسخهٔ در حال اجرا + SHA256 بسته.
2. **Backup قبل از deployment**: `wp db export` (یا بکاپ cPanel) + کپی `wp-content/uploads`.
   بدون backup موفق، deployment اجرا نشود.
3. Artifact جدید را verify (`SHA256SUMS`) و مستقر کنید.
4. **Post-deploy verification**: `wp eval-file tests/health_check.php` و در staging
   `wp eval-file tests/staging_smoke.php`. شکست = توقف زنجیره.

## ۳) اجرای rollback

اگر post-deploy verification شکست خورد:

1. Artifact نسخهٔ **قبلی (known-good)** را از Actions Artifacts (یا Release tag) بردارید —
   **همان zip، بدون build مجدد** — و `SHA256SUMS` آن را verify کنید.
2. محتوای `wp-content` نسخهٔ شکست‌خورده را با محتوای artifact قبلی جایگزین کنید
   (`wp-config.php` و `.htaccess` نیز از همان artifact).
3. `.env` دست نمی‌زنید (خارج از artifact است و secretها تغییری نکرده‌اند).
4. **Post-rollback health check**: `wp eval-file tests/health_check.php` باید PASS شود؛
   وگرنه بکاپ مرحلهٔ ۲ را برگردانید (آخرین راه).
5. رویداد را در deployment history ثبت کنید (نسخهٔ برگشته + دلیل).

## ۴) محدودیت‌های صریح (database)

- تنها تغییر schema دیتابیس پروژه، جدول outbox اعلان‌هاست
  (`wp_bamero_queue_notification`) که با `dbDelta` **در فعال‌سازی افزونه** ساخته می‌شود.
- این جدول **بدون key migration مخرب** است: نسخه‌های قبلی artifact با وجود جدول،
  کاملاً کار می‌کنند؛ rollback نیازمند down-migration دیتابیس **نیست**.
- WordPress core و WooCommerce خودشان schema را مدیریت می‌کنند؛ هنگام rollback به
  artifact قبلی، اگر core/WC در دیتابیس جدیدتر شده باشند، **بکاپ DB مرحلهٔ ۲**
  تنها مسیر بازگشت کامل است — به همین دلیل backup قبل از deployment اجباری است.

## ۵) وضعیت verification

- ساخت artifact، چک‌سام و ردیابی commit: ✅ خودکار در CI قابل اثبات.
- اجرای واقعی rollback (استقرار مجدد artifact قبلی روی هاست واقعی + health check):
  **NOT VERIFIED** تا زمانی که هاست production مشخص و دسترس‌پذیر شود.
