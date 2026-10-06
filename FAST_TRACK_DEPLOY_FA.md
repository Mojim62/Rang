# 🚀 استقرار سریع بامرو — مسیر ۳۰ دقیقه‌ای

> این فایل نسخهٔ فشردهٔ [`GO_LIVE_PHP_HOSTING_FA.md`](GO_LIVE_PHP_HOSTING_FA.md) است. اگر به جزئیات بیشتر نیاز داشتید به آن مراجعه کنید.

## ۰) پیش‌نیازها
- هاست اشتراکی PHP 8.3+ با MySQL و SSL (ترجیحاً cPanel با ترمینال/SSH و wp-cli)
- دامنه وصل‌شده + SSL فعال

## ۱) دیتابیس — cPanel → MySQL Database Wizard
نام دیتابیس + کاربر + رمز → کاربر با دسترسی ALL. سه مقدار را یادداشت کنید.

## ۲) آپلود
```bash
git clone https://github.com/Mojig62m/Rang.git && cd rang
bash scripts/build-release.sh     # سازندهٔ canonical — خروجی: dist/bamero-release.zip
# یا مستقیم: فقط wp-config.php و wp-content را آپلود کنید
```
آپلود zip در public_html و Extract.

## ۳) ساخت خودکار `.env` (بدون کپی دستی saltها!)
```bash
# بیرون از webroot (یک پوشه بالاتر از public_html) — یا داخل آن (.htaccess بلاک می‌کند)
DB_NAME=... DB_USER=... DB_PASSWORD=... WP_HOME=https://دامنه \
    bash public_html/scripts/make-env.sh ../.env
```
سپس فقط `ZARINPAL_MERCHANT_ID` و کلیدهای `SMS.ir` را در آن بگذارید.

## ۴) نصب وردپرس
`https://دامنه/wp-admin/install.php` → فقط عنوان/مدیر (نام کاربری **admin** ممنوع).

## ۵) bootstrap یک‌باره (staging / اولین راه‌اندازی) — یک دستور
```bash
cd public_html && bash scripts/quick-install.sh
```
> ⚠️ نقش این اسکریپت **bootstrap یک‌بارهٔ staging/نصب اولیه** است — نه مسیر deployment برای production. برای به‌روزرسانی production همیشه از همان **artifact واحد** (`bamero-release.zip` + `sha256sum -c SHA256SUMS`) استفاده کنید؛ هرگز برای deployment به نصب لحظه‌ای plugin یا خاموش‌کردن `DISALLOW_FILE_MODS=1` وابسته نباشید.
این دستور پشت‌سرهم: ووکامرس را نصب/فعال می‌کند، تم بامرو و هر ۶ افزونهٔ پروژه را فعال می‌کند، پیوندها/منطقهٔ زمانی/ارز ریال را تنظیم می‌کند و در پایان دود-تست سلامت را اجرا می‌کند.

> اگر wp-cli نیست: در cPanel از «Terminal» استفاده کنید یا `curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && php wp-cli.phar ...`

## ۶) تست نهایی (فقط این را رد نکنید)
- درگاه زرین‌پال فعال + یک **پرداخت واقعی کم‌مبلغ** تا verify و پیامک OTP تست شود.
- سفارش آزمایشی: وضعیت باید به «پرداخت‌شده» تغییر کند.

## بعد از go-live (فوریت ندارد)
- Wordfence + WP Super Cache (و `WP_CACHE=1` در .env)
- ثبت اینماد، Search Console/sitemap
- بکاپ خودکار هاست را فعال کنید

## اشتباهات رایج
| نشانه | راه‌حل سریع |
|---|---|
| صفحهٔ نصب دیتابیس می‌خواهد | `.env` پیدا نمی‌شود — مسیرش را در wp-config چک کنید |
| quick-install خطای file_mods داد | در `.env` موقتاً `DISALLOW_FILE_MODS=0`، بعد دوباره `1` |
| درگاه «پیکربندی نشده» | `ZARINPAL_MERCHANT_ID` در `.env` خالی است |
| دود-تست FAIL جدول outbox | افزونهٔ production-core را یک‌بار غیرفعال/فعال کنید (dbDelta) |