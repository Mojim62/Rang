# فروشگاه اینترنتی بامرو

## توضیحات

فروشگاه اینترنتی **بامرو** یک پروژه **WordPress + WooCommerce** برای فروش رنگ ساختمانی، پوشش‌ها، زیرسازی، عایق/محافظ، ابزار و محصولات مرتبط در بازار ایران است. این بسته شامل Theme سفارشی، افزونه‌های هسته، کنترل‌های امنیتی، آداپتور SMS.ir و درگاه زرین‌پال و گیت‌های قابل‌تکرار کد است.

> **وضعیت تحویل:** آمادهٔ استقرار روی **هاست اشتراکی PHP**. کلیدهای زرین‌پال، SMS.ir، دامنه و اطلاعات پایگاه‌داده به‌صورت **placeholder** باقی مانده‌اند تا مالک پروژه در پایان آن‌ها را در فایل `.env` جای‌گذاری کند. راهنمای گام‌به‌گام در [`GO_LIVE_PHP_HOSTING_FA.md`](GO_LIVE_PHP_HOSTING_FA.md) آمده است.

## ویژگی‌ها

### ✅ ویژگی‌های کلی
- **پشتیبانی کامل از RTL و زبان فارسی** (fa-IR).
- **طراحی حرفه‌ای و مدرن** با تم آبی، سفید و طلایی.
- **واکنش‌گرا** (Responsive) برای تمام دستگاه‌ها (موبایل، تبلت، دسکتاپ).
- **بهینه‌سازی‌شده برای موتورهای جستجو** (SEO).
- **امنیتی بالا** با تنظیمات کامل.
- **عملکرد بهینه** با کش، فشرده‌سازی و CDN.

### ✅ ویژگی‌های WooCommerce
- **دسته‌بندی‌ها و برچسب‌های محصولات** برای سازماندهی بهتر.
- **مرور و دسته‌بندی محصولات** با آرشیو دسته و برچسب؛ فیلتر قیمت استاندارد ووکامرس در دسترس است (فیلتر رنگ/برند در نقشه راه).
- **سبد خرید و تسویه حساب** ساده و کاربرپسند.
- **آداپتور رسمی زرین‌پال** برای request، callback و verify؛ فعال‌سازی با environment.
- **روش‌های ارسال** (پست، تیپاکس، تحویل در محل).
- **لیست علاقه‌مندی** با پلاگین YITH WooCommerce Wishlist.

### ✅ ویژگی‌های احراز هویت (موبایل‌محور)
- ورود/ثبت‌نام **تنها با شمارهٔ موبایل و کد یک‌بارمصرف (OTP)** از طریق SMS.ir.
- بدون رمز عبور، بدون ایمیل مشتری؛ ایمیل داخلی غیرقابل‌مسیریابی برای سازگاری با اسکیمای WordPress.
- محدودیت نرخ، انقضای کوتاه کد، حداکثر تلاش و قفل موقت.

### ✅ ویژگی‌های امنیتی
- **کلیدهای امنیتی** برای محافظت در برابر حملات.
- **محروم کردن دسترسی** به فایل‌های حساس (wp-config.php, xmlrpc.php).
- **غیرفعال کردن ویرایش فایل‌ها** از پنل مدیریت.
- **غیرفعال کردن XML-RPC** برای جلوگیری از حملات Brute Force.
- **مخفی کردن ورژن WordPress** برای کاهش خطرات امنیتی.
- **هدرهای امنیتی** و **CSP با nonce** برای اسکریپت‌ها.

### ✅ ویژگی‌های عملکرد
- **کش سرور** با WP Super Cache (اختیاری؛ پرچم `WP_CACHE` پس از نصب افزونه در `.env` فعال می‌شود).
- **فشرده‌سازی Gzip** برای کاهش حجم فایل‌ها.
- **Lazy Load** برای تصاویر.
- **Minify CSS/JS** اختیاری با افزونهٔ پیشنهادی Autoptimize (پیش‌فرض: بدون تغییر فایل‌ها، `CONCATENATE_SCRIPTS=false`).
- **Preload و Preconnect** برای فایل‌های حیاتی.

### ✅ ویژگی‌های SEO
- **Schema Markup** (JSON-LD با nonce) برای محصولات و صفحات.
- **Canonical URL** خودکار وردپرس/ووکامرس.
- **Open Graph و Twitter Cards** با افزونهٔ پیشنهادی Rank Math.
- **Sitemap** خودکار با Rank Math.
- **robots.txt پویا** (دامنه‌محور؛ بدون هاست hard-code).

### ✅ ویژگی‌های دسترسی‌پذیری
- **کیبورد ناوبری** برای کاربران با نیازهای ویژه.
- **ARIA Labels** برای عناصر تعاملی.
- **Skip to Content Link** برای کاربران صفحه‌خوان.
- **کنتراست رنگ** مناسب برای خوانایی.

## ساختار پروژه

```
Rang/
├── wp-config.php                 # تنظیمات پایه WordPress + بارگذار .env (fail-closed)
├── .htaccess                     # قوانین سرور، امنیت و کش
├── .env.example                  # الگوی متغیرهای محیطی (کپی به .env و تکمیل کنید)
├── LICENSE                       # مجوز MIT
├── SECURITY.md                   # سیاست امنیتی و کانال گزارش آسیب‌پذیری
├── .github/workflows/            # CI استاتیک + فشرده‌سازی خودکار تصاویر (pngquant)
├── .github/dependabot.yml        # پایش دوره‌ای وابستگی‌ها
├── README.md                     # مستندات پروژه
├── GO_LIVE_PHP_HOSTING_FA.md     # راهنمای گام‌به‌گام استقرار روی هاست PHP
├── docs/                         # ADR معماری + مدل تهدید (از استقرار عمومی مستثناست)
├── tests/                        # گیت‌های CI + دود-تست استیجینگ (staging_smoke.php)
├── scripts/build-deploy.sh       # ساخت بستهٔ استقرار تمیز (zip بدون docs/tests)
└── wp-content/
    ├── uploads/.htaccess         # ممنوعیت اجرای PHP در پوشهٔ آپلود
    ├── themes/
    │   └── bamero/               # تم سفارشی بامرو
    │       ├── css/              # متغیرها، آیکون‌ها، استایل‌ها، WooCommerce و RTL
    │       ├── js/               # اسکریپت‌های اصلی
    │       ├── images/           # لوگو و فاوآیکون
    │       ├── assets/fonts/     # فونت Vazirmatn (woff2 + مجوز OFL)
    │       ├── woocommerce/      # قالب‌های بازنویسی‌شده WooCommerce
    │       ├── functions.php     # توابع تم
    │       ├── header.php / footer.php / index.php / front-page.php / page.php
    │       └── 404.php / about.php / contact.php
    └── plugins/
        ├── bamero-production-core/    # هستهٔ production (امنیت، outbox پیامک، health)
        ├── bamero-mobile-auth/        # احراز هویت موبایل‌محور (OTP)
        ├── bamero-zarinpal-gateway/   # درگاه پرداخت زرین‌پال v4
        ├── bamero-woocommerce-setup/  # کاتالوگ اولیه (۲۰ محصول + تصاویر) و تنظیمات ووکامرس
        ├── bamero-custom-plugin/      # ساخت صفحات محتوا
        └── bamero-essential-plugins/  # نصب‌کنندهٔ افزونه‌های پیشنهادی
```

## نصب و راه‌اندازی (هاست PHP)

> راهنمای کامل و گام‌به‌گام در [`GO_LIVE_PHP_HOSTING_FA.md`](GO_LIVE_PHP_HOSTING_FA.md). خلاصه:

### 1. دریافت کد
```bash
git clone https://github.com/Mojig62m/rang.git
cd Rang
```

### 2. آپلود روی هاست
- هستهٔ WordPress (نسخهٔ fa_IR) را در `public_html` قرار دهید.
- پوشهٔ `wp-content` این بسته را روی `wp-content` هاست بازنویسی کنید.
- فایل‌های `wp-config.php` و `.htaccess` را در ریشهٔ سایت قرار دهید.

### 3. پیکربندی متغیرهای محیطی
```bash
cp .env.example .env
# مقادیر DB، saltها، ZARINPAL_MERCHANT_ID، SMS_IR_API_KEY و ... را پر کنید.
```
فایل `.env` را **بیرون از web root** (مثلاً `../bamero.env`) یا داخل ریشه (که با `.htaccess` مسدود است) آپلود کنید. `wp-config.php` آن را خودکار می‌خواند.

### 4. نصب WordPress
- به `https://YOUR-DOMAIN/wp-admin/install.php` بروید و مراحل نصب را انجام دهید.
- در صورت تمایل `WP_HOME`/`WP_SITEURL` را در `.env` تنظیم کنید تا دامنه بدون دست‌کاری پایگاه‌داده پین شود.

### 5. فعال‌سازی تم و افزونه‌ها
- تم **بامرو** را فعال کنید.
- افزونه‌های همراه را فعال کنید: `bamero-production-core`، `bamero-mobile-auth`، `bamero-zarinpal-gateway`، `bamero-woocommerce-setup`، `bamero-custom-plugin`، `bamero-essential-plugins`.
- WooCommerce و سایر افزونه‌های پیشنهادی را نصب کنید.

### 6. پیکربندی نهایی
- **Permalinks**: به **Post Name** تغییر دهید.
- **Site Address**: آدرس سایت را تنظیم کنید (یا از `WP_HOME` استفاده کنید).
- **Timezone**: به **Tehran (UTC+3:30)** تغییر دهید.
- **درگاه زرین‌پال و SMS.ir**: secretها و template IDها را از `.env` تأمین کنید؛ سپس callback/verify و OTP را تست کنید.
- **روش‌های ارسال**: در WooCommerce تنظیم کنید.

## تست و اعتبارسنجی

برای اجرای گیت‌های قابل‌تکرار سطح کد:
```bash
bash tests/production_gate.sh
```
این دستور شامل lint سینتکس PHP، تشخیص اعلان تکراری توابع، اسکن secretهای hard-code و پوشش nonce در JSON-LD است. پیش‌نیاز آن `PHP CLI` است.

> این گیت عمداً ادعای موفقیت runtime (پرداخت، SMS، Lighthouse یا restore) را ایجاد نمی‌کند؛ این موارد باید در staging مجاز با شواهد اجرا شوند.

### دود-تست استیجینگ (پیش از go-live)

پس از استقرار روی استیجینگ و فعال‌سازی افزونه‌ها، سلامت محیط را با این دستور اثبات کنید:
```bash
wp eval-file tests/staging_smoke.php
```
این تست ارز **ریال (IRR)**، کاتالوگ ۲۰ محصولی، درگاه زرین‌پال، جدول اعلان‌های پایگاه‌داده و صفحات حقوقی را بررسی می‌کند.

### تست عملکرد
- [GTmetrix](https://gtmetrix.com/)
- [PageSpeed Insights](https://pagespeed.web.dev/)
- [WebPageTest](https://www.webpagetest.org/)

### تست امنیتی
- [Wordfence](https://www.wordfence.com/)
- [Sucuri](https://sucuri.net/)

### تست SEO
- [Google Search Console](https://search.google.com/search-console)
- [Rank Math](https://rankmath.com/)

### تست دسترسی‌پذیری
- [WAVE](https://wave.webaim.org/)
- [axe](https://www.deque.com/axe/)

## مستندسازی

برای اطلاعات بیشتر، به فایل‌های زیر مراجعه کنید:
- [GO_LIVE_PHP_HOSTING_FA.md](GO_LIVE_PHP_HOSTING_FA.md) - راهنمای استقرار روی هاست PHP
- [SECURITY.md](SECURITY.md) - سیاست امنیتی و گزارش آسیب‌پذیری
- [LICENSE](LICENSE) - متن مجوز MIT
- [docs/adrs/0001-architecture.md](docs/adrs/0001-architecture.md) - تصمیمات معماری (ADR)
- [docs/wp-threat-model.md](docs/wp-threat-model.md) - مدل تهدید امنیتی
- [wp-content/themes/bamero/README.md](wp-content/themes/bamero/README.md) - مستندات تم

## مشارکت

برای مشارکت در پروژه، لطفاً یک Pull Request ارسال کنید.

## مجوز

این پروژه تحت مجوز **MIT** قرار دارد.