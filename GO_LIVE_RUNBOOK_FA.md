# رانبوک راه‌اندازی حرفه‌ای فروشگاه بامرو (Go-Live Runbook)

این سند لایهٔ عملیاتی روی مستندات موجود است: راهنمای میزبانی (GO_LIVE_PHP_HOSTING_FA.md)، خط لولهٔ استقرار (docs/DEPLOYMENT_PIPELINE_FA.md) و مانیتورینگ (docs/HOSTING_OPTIONS_FA.md). هدف: کمترین خطا در روز راه‌اندازی واقعی.

---

## ۱) معماری توصیه‌شده

- سه محیط: local (Docker)، staging (ساب‌دامنه)، production (دامنهٔ اصلی).
- استقرار فقط از طریق خط لولهٔ GitHub (build-once → staging → verify → production با تأیید). هیچ تغییری دستی روی production انجام نمی‌شود؛ هر تغییر = PR → گیت سبز → merge → deploy.
- ساختار releaseها روی هاست: دایرکتوری‌های نسخه‌دار + سیم‌لینک current (خروجی اسکریپت deploy-release.sh) — بازگشت فوری با rollback بدون ساخت مجدد.
- قانون طلایی: آرتیفکت یک بار ساخته می‌شود و بدون تغییر به هر دو محیط ارتقا می‌یابد (SHA256 در verify).

## ۲) انتخاب هاست (معیارهای تعیین‌کننده)

| معیار | حداقل قابل قبول | ترجیح حرفه‌ای |
|---|---|---|
| PHP | 8.3 با FPM | 8.3 + OPcache فعال |
| وب‌سرور | Apache + mod_headers/mod_expires | LiteSpeed (سرعت ووکامرس) |
| دیتابیس | MariaDB 10.11+ / MySQL 8.0 با utf8mb4 | + ابزار بهینه‌سازی (mysqltuner) |
| کش سرور | — | Redis اختصاصی برای Object Cache |
| دسترسی | SFTP + WP-CLI | SSH کامل (لازم برای خط لوله) |
| بکاپ | روزانه هاست | + کپی هفتگی بیرونی (restore تست شود) |

نکته: خط لولهٔ استقرار به SSH و WP-CLI روی هاست نیاز دارد؛ هاستی انتخاب کنید که هر دو را می‌دهد یا پشتیبانی آن فعال کند.

## ۳) تنظیمات سطح هاست (PHP و کرن)

### OPcache (در php.ini / پنل هاست)

```
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

### کرن واقعی به‌جای WP-Cron

۱) در فایل `.env` مقدار `DISABLE_WP_CRON=1` بگذارید (پشتیبانی از wp-config.php نسخهٔ جدید اضافه شده).
۲) در پنل هاست (Cron Jobs) برای کاربر هاست این خط را هر ۵ دقیقه تنظیم کنید:

```bash
*/5 * * * * cd "$HOME/PATH_TO_CURRENT" && wp cron event run --due-now --quiet >> "$HOME/wp-cron.log" 2>&1
```

PATH_TO_CURRENT همان مسیر سیم‌لینک current است (مقدار DEPLOY_PATH/current). Action Scheduler ووکامرس (پردازش سفارش‌ها، ایمیل‌ها) با همین کرن اجرا می‌شود؛ پس از راه‌اندازی یک‌بار صحت اجرا را در WooCommerce > Status > Scheduled Actions بررسی کنید.

### محدودیت‌های PHP
upload_max_filesize=64M، post_max_size=64M، memory_limit=256M، max_execution_time=300 (این‌ها تنظیم پنل هاست هستند، نه .env).

## ۴) پیکربندی GitHub (پیش‌نیاز خط لوله) — ۴۵ دقیقه

۱) کلید استقرار بسازید:
```bash
ssh-keygen -t ed25519 -f bamero_deploy_key -C "bamero-deploy" -N ""
```
۲) محتوای `bamero_deploy_key.pub` را در `~/.ssh/authorized_keys` کاربر دپلوی روی هاست اضافه کنید؛ فایل خصوصی در GitHub استفاده می‌شود.
۳) در Settings → Environments دو محیط بسازید: `staging` و `production` (روی production حتماً Required reviewers را فعال کنید). در هر محیط این Secretها را ثبت کنید:

| Secret | توضیح |
|---|---|
| DEPLOY_SSH_KEY | محتوای فایل خصوصی bamero_deploy_key |
| DEPLOY_HOST | IP یا هاست‌نیم سرور |
| DEPLOY_USER | کاربر SSH دپلوی |
| DEPLOY_PATH | مسیر ریشهٔ استقرار (شامل releases/ و current) |
| STAGING_URL / PRODUCTION_URL | آدرس کامل https برای verify (به‌عنوان Secret محیطی) |

۴) در Settings → Secrets and variables → Variables دو متغیر بسازید: `PRODUCTION_URL` و `STAGING_URL` (برای مانیتور شبانه؛ فایل health-monitor.yml همین‌ها را می‌خواند).
۵) Settings → Branches → قاعدهٔ حفاظت شاخه main: لازم‌بودن static-production-gate + یک review.
۶) پس از تست موفق اولین deploy دستی، در `.github/workflows/deploy.yml` تریگر push به main را مطابق کامنت فایل فعال کنید (استقرار خودکار).

## ۵) کش چندلایه (ترفند کلیدی سرعت ووکامرس فارسی)

لایه ۱ — Object Cache (اگر Redis دارید): افزونهٔ Redis Object Cache را نصب و `wp redis enable` کنید؛ تنظیمات WP_REDIS_* از .env خوانده می‌شوند. سشن‌های سبد خرید ووکامرس روی Redis سبک می‌شوند.

لایه ۲ — Page Cache با استثناهای ووکامرس (مهم‌ترین تنظیم): صفحات و کوکی‌های زیر هرگز کش نشوند:

```
/cart/    /checkout/    /my-account/    /wp-json/*    *add-to-cart=*
cookie: wordpress_logged_in_*   woocommerce_items_in_cart*   wp-postpass_*
```

- روی هاست LiteSpeed: افزونهٔ LiteSpeed Cache (ESI برای سبد خرید).
- روی Apache: WP Super Cache یا Autoptimize مطابق GO_LIVE_PHP_HOSTING_FA.md؛ بعد از فعال‌سازی، WP_CACHE=1 در .env.
- کش کاربرِ لاگین‌شده باید کاملاً خاموش باشد (نقش مشترک قیمت و سبد).

لایه ۳ — Browser cache: از قبل در .htaccess پروژه تنظیم شده (CSS/JS یک ماه، تصاویر/فونت یک سال).

## ۶) CDN ایرانی (ArvanCloud) — قواعد دقیق

- دامنه را در آروان اضافه کنید؛ SSL آروان فعال؛ DNS مطابق راهنمای آروان.
- Cache Bypass برای مسیرهای بخش ۵ (سبد، تسویه، حساب کاربری، wp-json).
- TTL صفحهٔ HTML کوتاه (۳ تا ۵ دقیقه)، TTL استاتیک‌ها یک سال (نام فایل‌های هش‌دار).
- WAF آروان: Rate limit روی wp-login.php (مثلاً ۱۰ درخواست/دقیقه/IP) و مسدودسازی درخواست‌های خارج از ایران اگر بازار فروش فقط داخلی است.
- بعد از هر deploy، پاک‌سازی کش CDN (Purge) برای صفحهٔ اصلی و محصولات.

## ۷) زرین‌پال — چک‌لیست go-live

1. مرچنت‌کد واقعی در .env (ZARINPAL_MERCHANT_ID) و آدرس درگاه production (payment.zarinpal.com)؛ sandbox فقط برای پیش از راه‌اندازی.
2. دامنهٔ callback باید دقیقاً همان دامنهٔ ثبت‌شده در پنل زرین‌پال باشد (درگاه‌ها به دامنه قفل‌اند).
3. مقیاس مبلغ: درگاه ریالی است (IRR). مطمئن شوید قیمت محصولات به ریال است، نه تومان — شایع‌ترین خطای go-live فروشگاه‌های ایرانی همین است.
4. اولین تراکنش واقعی با مبلغ کم (مثلاً ۱۰٬۰۰۰ ریال) انجام و وضعیت سفارش را «پرداخت‌شده» و ثبت authority بررسی کنید؛ سپس مبلغ را برگشت بدهید.
5. تست رفرش مکرر صفحهٔ callback (idempotency) — یک‌بار با دو بار رفرش تأیید کنید که سفارش تکراری ثبت نشود.

## ۸) SMS.ir — چک‌لیست go-live

1. قالب‌های پنج‌گانه (ورود OTP، پردازش، تحویل، لغو، خطای پرداخت) باید در پنل SMS.ir تأیید شده باشند؛ نام پارامترها (Code، OrderId، Name) باید دقیقاً با قالب هم‌خوانی داشته باشد.
2. برای OTP از سرویس Verify (ارسال سریع با قالب تأییدشده) استفاده شود؛ خط ارسال فعال باشد.
3. قبل از راه‌اندازی یک تست واقعی با شمارهٔ موبایل خودتان: دریافت OTP، ورود، و ارسال اطلاع‌رسانی سفارش.
4. پرچم‌های BAMERO_ENABLE_PAYMENT_IN_STAGING و BAMERO_ENABLE_SMS_IN_STAGING در production خاموش بمانند.

## ۹) روز راه‌اندازی (Launch Day) — ترتیب دقیق

پنجرهٔ پیشنهادی: صبح زود (کم‌ترین ترافیک).

1. بکاپ کامل دستی از پنل هاست (فایل + دیتابیس) و دانلود آن روی سیستم شخصی. تست restore روی local یک‌بار تمرین شده باشد.
2. DNS ساب‌دامنهٔ staging و production قطعی شده و SSL هر دو سبز باشد (بررسی با ابزار آنلاین SSL).
3. در GitHub → Actions → Deployment pipeline → Run workflow (بدون rollback)؛
4. تأیید staging-verify سبز؛ تست دستی سریع روی staging: جستجو، افزودن به سبد، my-account.
5. تأیید (Approve) مرحلهٔ production توسط reviewer.
6. production-verify سبز (صفحهٔ اصلی، wp-json، shop، cart، my-account).
7. تست‌های واقعی روی production: ثبت‌نام/ورود با OTP → افزودن محصول به سبد → تسویه و پرداخت واقعی کم‌مبلغ با زرین‌پال → تأیید سفارش «پرداخت‌شده» → دریافت SMS اطلاع‌رسانی.
8. Core Web Vitals: تست PageSpeed موبایل صفحهٔ اصلی + یک صفحهٔ محصول (هدف: LCP زیر ۲٫۵ ثانیه).
9. ثبت sitemap در Google Search Console و Bing؛ robots.txt پویا.
10. توییت/اعلام عمومی فقط بعد از همهٔ مراحل قبل.

معیارهای بازگشت فوری (rollback):
- verify شکست بخورد؛
- تست پرداخت واقعی شکست بخورد یا سفارش پرداختی ثبت نشود؛
- خطای PHP قابل‌مشاهده در صفحات کلیدی.
در همهٔ این حالات: Actions → Deployment pipeline → Run workflow → rollback-production (سوییچ به release قبلی، بدون ساخت مجدد) و issue ثبت‌شدهٔ مانیتور شبانه را بررسی کنید.

## ۱۰) پایش پس از راه‌اندازی (هفتهٔ اول)

- روزانه: Health check پنل هاست + تب Actions مانیتور شبانه (issue خودکار در شکست).
- ابری: آروان مانیتورینگ یا یک uptime-check خارجی روی صفحهٔ اصلی + wp-json.
- اندپوینت سلامت داخلی: `curl -s "https://دامنه/health-check.php?token=TOKEN" | jq .` — فایل health-check.php همراه بستهٔ استقرار منتقل می‌شود؛ در production الزاماً `BAMERO_HEALTH_TOKEN` را در .env تنظیم کنید (بدون توکن پاسخ ۴۰۳ است و هرگز سلامت کاذب نمی‌دهد).
- لاگ error هاست را روز اول و روز سوم مرور کنید؛ خطای تکراری = issue گیت.
- بکاپ: تمدید خودکار هاست فعال؛ یک‌بار در ماه فایل بکاپ را واقعاً restore- تست کنید.
- پس از اولین ارتقای major ووکامرس: بازبینی WooCommerce > Status برای هشدار قالب‌های قدیمی و دود-تست سبد/تسویه.

## ۱۱) اطلاعاتی که برای نهایی‌کردن راه‌اندازی لازم است (پاسخ این پرسشنامه)

| # | پرسش | چرا لازم است |
|---|---|---|
| 1 | هاست انتخابی؟ نوع وب‌سرور (Apache/LiteSpeed)؟ Redis دارد؟ | انتخاب لایهٔ کش و اسکریپت‌ها |
| 2 | دامنهٔ نهایی + ترجیح www یا غیرwww + دسترسی DNS کجاست (آروان؟) | SSL، CDN، قفل درگاه |
| 3 | ساب‌دامنهٔ staging مدنظر چیست؟ | پیکربندی GitHub environment |
| 4 | مرچنت‌کد زرین‌پال آماده است؟ دامنه در پنل زرین‌پال ثبت شده؟ | go-live درگاه |
| 5 | کلید API SMS.ir + شناسهٔ قالب‌های تأییدشده؟ | اطلاع‌رسانی و OTP |
| 6 | SMTP ادمین از کجا تأمین می‌شود؟ | اعلان‌های مدیر |
| 7 | هاست SSH و WP-CLI می‌دهد؟ کاربر دپلوی ساخته شده؟ | خط لولهٔ استقرار |
| 8 | تاریخ/ساعت مطلوب پنجرهٔ راه‌اندازی؟ | هماهنگی rollback و پایش |

پس از دریافت پاسخ‌ها، پیکربندی GitHub (بخش ۴) و اجرای روز راه‌اندازی (بخش ۹) بدون ابهام قابل انجام است.