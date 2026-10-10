# راهکارهای هاستینگ و استقرار — بامرو (۲۰۲۶-۱۰)

این سند گزینه‌های استقرار فروشگاه را مقایسه می‌کند و مسیر پر-اتوماسیون (پایپ‌لاین موجود مخزن) را گام‌به‌گام توضیح می‌دهد. اصول: یک artifact یک‌بار ساخته می‌شود (`scripts/build-release.sh`)، همان artifact با checksum به staging و production ترویج می‌شود، و هر gate شکست‌خورده کل زنجیره را متوقف می‌کند (fail-closed). جزئیات معماری در [`docs/DEPLOYMENT_PIPELINE_FA.md`](DEPLOYMENT_PIPELINE_FA.md).

## مقایسهٔ سریع

| گزینه | سرعت راه‌اندازی | سطح اتوماسیون | سازگاری زرین‌پال / SMS.ir | هزینه ماهانه | ریسک عملیاتی |
|---|---|---|---|---|---|
| ۱) هاست اشتراکی ایرانی (cPanel/DirectAdmin) با SSH | سریع‌ترین (نیم‌روز) | متوسط (در صورت وجود SSH + wp-cli، کامل) | کامل | کم | کم؛ محدودیت منابع |
| ۲) VPS ایرانی + پنل (aaPanel/CloudPanel) + پایپ‌لاین GitHub | نیم‌روز تا یک روز | بالاترین (توصیه‌شده) | کامل | متوسط | متوسط؛ نگهداری سرور با مالک |
| ۳) هاست مدیریت‌شدهٔ خارج از کشور | کند | بالا | مشکل‌دار (تحریم، پرداخت ارزی، دسترسی callback درگاه) | بالا | بالا برای بازار ایران |
| ۴) Docker (docker-compose موجود مخزن) | سریع (فقط dev/staging) | بالا ولی خودگردان | برای تست داخلی | بسته به زیرساخت | بالا برای production |

**توصیهٔ صریح:** برای go-live واقعی، گزینهٔ ۱ سریع‌ترین مسیر و گزینهٔ ۲ بالاترین سطح اتوماسیون و پایداری است. گزینهٔ ۳ برای این پروژه توصیه نمی‌شود: درگاه زرین‌پال و SMS.ir هر دو ایرانی هستند و دسترسی پایدار سرور به آن‌ها الزامی است؛ پرداخت ارزی و ریسک تعلیق سرویس نیز مضاعف است. گزینهٔ ۴ فقط برای استیجینگ و توسعه.

## گزینهٔ ۱ — هاست اشتراکی ایرانی + استقرار از artifact

مناسب برای سریع‌ترین go-live. پیش‌نیازها: PHP 8.3، MariaDB/MySQL، SSL، SSH فعال (jailshell کافی است)، ترجیحاً wp-cli.

- مسیر نیمه‌خودکار (بدون pip): اجرای `bash scripts/build-release.sh`، آپلود zip خروجی (یا artifact دانلودشده از Actions) در File Manager و استخراج در docroot طبق `GO_LIVE_PHP_HOSTING_FA.md`.
- مسیر خودکار (اگر هاست SSH و wp-cli می‌دهد): همان پایپ‌لاین گزینهٔ ۲ بدون تغییری کار می‌کند. اگر symlink روی هاست اشتراکی محدود بود، `DEPLOY_PATH` را دایرکتوری اختصاصی بگذارید و docroot وب‌سرور را به `current` اشاره دهید؛ در بدترین حالت به مسیر نیمه‌خودکار برگردید.

## گزینهٔ ۲ — VPS + پنل + پایپ‌لاین کامل (توصیه‌شده برای اتوماسیون)

زیرساخت: VPS ایرانی (Ubuntu 24.04)، پنل رایگان aaPanel یا CloudPanel، PHP 8.3-FPM، MariaDB 10.11+، nginx یا Apache، SSL خودکار. از این نقطه به بعد پایپ‌لاین مخزن (`.github/workflows/deploy.yml`) همه‌چیز را خودکار انجام می‌دهد: build یک‌بار، deploy به staging، راستی‌آزمایی HTTP، تأیید انسانی، deploy به production همراه با پشتیبان اجباری پایگاه‌داده، و بازگشت (rollback) یک‌کلیکی.

### گام‌های فعال‌سازی (حدود ۶۰ دقیقه، یک‌بار)

۱) آماده‌سازی سرور:
```bash
adduser deploy && usermod -aG www-data deploy
apt-get update && apt-get install -y unzip curl
curl -sS -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x /usr/local/bin/wp
mkdir -p /srv/bamero/releases
```
۲) bootstrap نخستین release: هستهٔ WordPress fa_IR را در `/srv/bamero/releases/production-0001` استخراج کنید؛ `wp-config.php`، `.htaccess` و `wp-content/` را از artifact اول (خروجی `scripts/build-release.sh`) روی آن overlay کنید؛ symlink را بسازید (`ln -sfn releases/production-0001 /srv/bamero/current`) و docroot وب‌سرور را به `/srv/bamero/current` تنظیم کنید. دیتابیس و `.env` طبق `GO_LIVE_PHP_HOSTING_FA.md`.
۳) کلید SSH اختصاصی استقرار: `ssh-keygen -t ed25519 -f bamero-deploy`؛ کلید عمومی در `~deploy/.ssh/authorized_keys`؛ کلید خصوصی فقط در secret GitHub (هرگز در مخزن).
۴) در GitHub UI: Settings → Environments → ساخت `staging` و `production`؛ برای production فعال‌کردن Required reviewers. secretهای هر environment دقیقاً با این نام‌ها: `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH`, و `STAGING_URL` / `PRODUCTION_URL`.
۵) اجرای استقرار: تب Actions → «Deployment pipeline (staging → production)» → Run workflow. اجرا تا staging-verify پیش می‌رود؛ پس از بازبینی و تأیید انسانی، production deploy با پشتیبان خودکار DB اجرا می‌شود.
۶) راستی‌آزمایی: `scripts/verify-deployment.sh` خودکار اجرا می‌شود؛ به‌علاوه روی host این دستور را اجرا کنید: `wp eval-file tests/staging_smoke.php` (ارز IRR، کاتالوگ ۲۰ محصولی، درگاه، جدول اعلان‌ها).
۷) بازگشت: در همان workflow ورودی `run_rollback` را روی `rollback-staging` یا `rollback-production` بگذارید — فقط symlink برمی‌گردد؛ بدون rebuild و بدون تغییر DB.

### چک‌لیست پس از استقرار (پیش از انتشار عمومی)

- [ ] Permalinks روی «Post name» و بازسازی؛ Timezone روی Tehran.
- [ ] WooCommerce → Status → Templates بدون پرچم outdated؛ گزارش HPOS بدون «unverified».
- [ ] تست حقیقی OTP پیامکی و یک پرداخت کوچک واقعی زرین‌پال (سپس سفارش تست را حذف کنید).
- [ ] بازبینی چشمی صفحات اصلی در عرض ۳۶۰ / ۷۶۸ / ۱۴۴۰ پیکسل؛ تست لایت‌باکس و زوم گالری محصول.
- [ ] یک‌بار تست rollback روی staging.
- [ ] HTTPS اجباری و ریدایرکت یکنواخت دامنه (www یا غیر آن).


## پایش خودکار شبانه (لایهٔ چهارم — ۲۰۲۶-۱۰)

گردش‌کار جدید `health-monitor.yml` هر شب (۰۰:۴۷ به وقت تهران) URLهای staging و production را با همان اسکریپت راستی‌آزمایی استقرار پروب می‌کند (صفحهٔ اصلی، wp-json، فروشگاه، سبد خرید، حساب کاربری) و در صورت شکست، یک issue ردیابی بدون تکرار باز می‌کند. برای فعال‌سازی، مالک فقط باید URLها را به‌عنوان **Repository Variables** (نه secret — URL عمومی اعتبارنامه نیست) با نام `PRODUCTION_URL` و `STAGING_URL` ثبت کند؛ تا آن زمان گردش‌کار بی‌صدا skip می‌شود (بدون نویز و بدون هشدار کاذب). به‌علاوه، گیت static اکنون `bash -n` روی تمام اسکریپت‌ها و `shellcheck` (سطح error) روی چهار اسکریپت پایپ‌لاین استقرار اجرا می‌کند؛ اسکریپت استقرار نیز پشتیبان DB خالی را رد می‌کند و پس از تعویض symlink کش آبجکت را flush می‌کند.
## صادقانه: چه چیزی اثبات‌شده است و چه چیزی نه

اثبات‌شده (قطعی، بدون نیاز به هاست): گیت production سبز روی main، سلامت سینتکس PHP، نبود secret در artifact، سازگاری HPOS و گالری native ووکامرس، و رفع باگ مسیر HOME در `deploy-release.sh` (B6). اثبات‌نشده تا اجرای واقعی: تراکنش زرین‌پال، تحویل OTP، رندر مرورگر، قابل‌نوشتن‌بودن uploads و WP-Cron روی host، و restore واقعی از پشتیبان. این موارد در `GO_LIVE_VERIFICATION_REPORT.md` و بخش NOT VERIFIED سند پایپ‌لاین ثبت شده‌اند و تنها با اجرای استیجینگ قابل اثبات‌اند.
