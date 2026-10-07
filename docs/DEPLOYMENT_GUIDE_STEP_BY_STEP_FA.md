# راهنمای گام‌به‌گام استقرار بامرو — از مخزن GitHub تا هاستینگ
## (کلیک‌به‌کلیک، تایپ‌به‌تایپ، لحظه‌به‌لحظه)

> 🗄️ **یادداشت آرشیوی (۲۰۲۶-۱۰-۰۷):** این راهنما از شاخهٔ `fix/hosting-readiness-showstoppers` (PR #3/#4) به `main` منتقل شد تا با حذف شاخه، از دست نرود. اشاره‌های درون متن به «merge نشدن PR #3» تاریخی است — آن اصلاحات هم‌اکنون در `main` ادغام شده‌اند. برای فرایند استقرار فعلی، `docs/DEPLOYMENT_PIPELINE_FA.md` مرجع اصلی است.


**دامنهٔ هدف:** `https://rang.chasb.bamero.ir`
**استک:** WordPress 6.7.x + WooCommerce 9.x + PHP 8.3 + MariaDB 10.11 + Redis
**مخزن:** `https://github.com/Mojig62m/Rang`
**نکتهٔ کلیدی:** این مخزن یک **«لایهٔ افزونه + قالب» (overlay)** است و **هستهٔ وردپرس، دیتابیس و افزونه‌های شخص‌ثالث را در خود ندارد**. بنابراین ترتیب کار بسیار مهم است: **ابتدا وردپرس و ووکامرس نصب می‌شوند، سپس این لایه روی آن‌ها قرار می‌گیرد.**

> ⚠️ **هشدار پیش از شروع:** اگر کد را بدون رفع نقص مرگ‌آور (PR #3) مستقر کنید، سایت با `Fatal error: Cannot redeclare bamero_csp_nonce()` از کار می‌افتد. **حتماً ابتدا PR #3 را merge کنید** (بخش ۱).

---

## فهرست مراحل

- **فاز ۰:** آماده‌سازی محلی و دریافت کد
- **فاز ۱:** انتخاب و پیکربندی هاست
- **فاز ۲:** نصب هستهٔ WordPress
- **فاز ۳:** نصب WooCommerce
- **فاز ۴:** استقرار لایهٔ بامرو (افزونه‌ها + قالب)
- **فاز ۵:** پیکربندی متغیرهای محیطی (اسرار)
- **فاز ۶:** درون‌ریزی دیتابیس
- **فاز ۷:** فعال‌سازی افزونه‌ها و قالب
- **فاز ۸:** تنظیمات وردپرس و ووکامرس
- **فاز ۹:** پیکربندی درگاه زرین‌پال
- **فاز ۱۰:** پیکربندی پیامک SMS.ir
- **فاز ۱۱:** Redis و کش
- **فاز ۱۲:** SSL و HTTPS
- **فاز ۱۳:** تست‌های نهایی (Smoke Test)
- **فاز ۱۴:** Go-Live و پایش
- **فاز ۱۵:** Rollback (بازگشت)
- **پیوست الف:** اشتباهات رایج و راه‌حل
- **پیوست ب:** توصیه‌های حرفه‌ای

---

## فاز ۰ — آماده‌سازی محلی و دریافت کد

### گام ۰.۱ — کلون مخزن روی سیستم خودتان
ترمینال را باز کنید و تایپ کنید:
```bash
git clone https://github.com/Mojig62m/Rang.git bamero-deploy
cd bamero-deploy
```

### گام ۰.۲ — اطمینان از وجود اصلاحات (PR #3)
```bash
git log --oneline -3
```
باید کامیت مربوط به `fix: resolve deployment-blocking duplicate function declarations` را ببینید. اگر نیست:
```bash
git fetch origin
git checkout fix/hosting-readiness-showstoppers
```
> 💡 **توصیه:** پیش از استقرار، PR #3 را در گیت‌هاب merge کنید تا شاخهٔ `main` تمیز و آماده باشد.

### گام ۰.۳ — ساخت بستهٔ استقرار (
بدون فایل‌های dev)
```bash
# فقط فایل‌های لازم را بسته‌بندی می‌کنیم (بدون .git و docs و tests)
zip -r bamero-overlay.zip wp-content wp-config.php .htaccess robots.txt -x "*.DS_Store"
```
> ❌ **اشتباه رایج:** آپلود کل مخزن شامل `.git/` روی هاست. این کار امنیت را به‌شدت پایین می‌آورد و می‌تواند سورس کد شما را افشا کند. **هرگز `.git/` را آپلود نکنید.**

---

## فاز ۱ — انتخاب و پیکربندی هاست

### گام ۱.۱ — پیش‌نیازهای هاست (چک‌لیست انتخاب)
هاست شما **باید** این‌ها را داشته باشد:

| نیاز | مقدار موردنیاز | چرا |
|------|----------------|-----|
| PHP | **8.3** (حداقل 8.2) | کد از `declare(strict_types=1)` و توابع مدرن استفاده می‌کند |
| افزونهٔ PHP | `mbstring`, `curl`, `openssl`, `json`, `redis`, `intl` | SMS.ir و زرین‌پال و فارسی |
| دیتابیس | MariaDB 10.11+ یا MySQL 8+ | utf8mb4 |
| Redis | موجود (اختیاری ولی توصیه‌شده) | object cache |
| HTTPS | گواهی SSL رایگان (Let's Encrypt) | الزام HSTS |
| دسترسی | SSH یا FTP + cPanel/DirectAdmin | برای آپلود و دستورات wp-cli |

### گام ۱.۲ — تنظیم نسخهٔ PHP (cPanel)
1. وارد **cPanel** شوید.
2. به **MultiPHP Manager** بروید.
3. دامنهٔ `rang.chasb.bamero.ir` را انتخاب کنید.
4. از منوی کشویی، **PHP 8.3** را انتخاب کنید.
5. روی **Apply** کلیک کنید.

### گام ۱.۳ — فعال‌سازی افزونه‌های PHP
1. در cPanel به **Select PHP Version** یا **MultiPHP INI Editor** بروید.
2. تب **Extensions** را باز کنید.
3. این‌ها را تیک بزنید: `mbstring`, `curl`, `openssl`, `json`, `redis`, `intl`, `fileinfo`, `zip`.
4. ذخیره کنید.

### گام ۱.۴ — تنظیم سقف‌های PHP
1. به **MultiPHP INI Editor** بروید → حالت **Editor**.
2. مقادیر زیر را تنظیم کنید:
```ini
memory_limit = 256M
max_execution_time = 120
upload_max_filesize = 64M
post_max_size = 64M
max_input_vars = 5000
display_errors = Off
```
3. **Save** کنید.
> ⚠️ **اشتباه رایج:** `display_errors = On` روی پروداکشن. این کار مسیر فایل‌ها و اطلاعات حساس را به بازدیدکننده نشان می‌دهد. همیشه `Off`.

### گام ۱.۵ — ساخت دیتابیس
1. در cPanel به **MySQL Databases** بروید.
2. در بخش **Create New Database**، نامی مثل `bam
ero_shop` تایپ کنید → **Create Database**.
3. در بخش **MySQL Users**، کاربری مثل `bamero_user` بسازید و یک رمز **قوی** (حداقل ۱۶ کاراکتر، ترکیب حرف/عدد/نماد) تایپ کنید → **Create User**.
4. در بخش **Add User to Database**، کاربر را به دیتابیس اضافه کنید و **ALL PRIVILEGES** را تیک بزنید → **Make Changes**.
5. این سه مقدار را یادداشت کنید (بعداً لازم می‌شوند):
   - `DB_NAME` = `نام‌کاربری_bamero_shop` (cPanel پیشوند اضافه می‌کند)
   - `DB_USER` = `نام‌کاربری_bamero_user`
   - `DB_PASSWORD` = رمز قوی
   - `DB_HOST` = `localhost`

> 💡 **توصیه:** رمز دیتابیس را در یک Password Manager ذخیره کنید، نه در فایل متنی روی سیستم.

---

## فاز ۲ — نصب هستهٔ WordPress

### گام ۲.۱ — دانلود وردپرس
روی سیستم خودتان (یا با SSH روی هاست):
```bash
wget https://wordpress.org/wordpress-6.7.1.tar.gz
tar -xzf wordpress-6.7.1.tar.gz
```
> 💡 **توصیه:** نسخه را **pin** کنید (نسخهٔ ثابت)، نه «آخرین نسخه». این کار از شگفتی‌های ناشی از آپدیت خودکار جلوگیری می‌کند.

### گام ۲.۲ — آپلود هسته به هاست
1. با **File Manager** در cPanel یا با FTP (FileZilla) وارد `public_html` (یا پوشهٔ دامنه) شوید.
2. **محتوای** پوشهٔ `wordpress/` را (نه خود پوشه) در ریشهٔ دامنه آپلود کنید.
   - یعنی `wp-admin/`, `wp-includes/`, `index.php`, `wp-settings.php` باید مستقیم در ریشه باشند.

### گام ۲.۳ — اجرای نصب
1. در مرورگر بروید به: `https://rang.chasb.bamero.ir/wp-admin/install.php`
2. فرم نصب باز می‌شود. اطلاعات زیر را وارد کنید:
   - **Site Title:** بامرو
   - **Username:** یک نام کاربری **غیر از admin**
   - **Password:** رمز قوی
   - **Email:** ایمیل مدیر
3. **Install WordPress** را کلیک کنید.
4. ورود کنید.

> ⚠️ **اشتباه رایج:** استفاده از `admin` به‌عنوان نام کاربری. این نام پیش‌فرض هدف اصلی حملات brute-force است. همیشه نام کاربری سفارشی انتخاب کنید.

### گام ۲.۴ — نکتهٔ مهم دربارهٔ `wp-config.php`
فایل `wp-config.php` که وردپرس خودش می‌سازد، **جایگزین** فایل `wp-config.php` بامرو می‌شود. **هنوز کاری نکنید** — در فاز ۴ آن را با نسخهٔ بامرو عوض می‌کنیم. فقط برای اکنون نصب را کامل کنید.

---

## فاز ۳ — نصب WooC
ommerce

### گام ۳.۱ — نصب از پنل
1. در پیشخوان وردپرس به **Plugins → Add New** بروید.
2. در جستجو تایپ کنید: `WooCommerce`
3. روی **Install Now** کلیک کنید، سپس **Activate**.
4. ویزارد راه‌اندازی ووکامرس باز می‌شود.

### گام ۳.۲ — تنظیمات ویزارد ووکامرس
1. **Store Details:**
   - آدرس: ایران
   - واحد پول: **تومان (IRT)** یا ریال (مطابق `ZARINPAL_CURRENCY`)
2. **Industry:** انتخاب دسته (رنگ و پوشش).
3. **Product Types:** فیزیکی.
4. **Business Details:** رد کنید (skip) — چون درگاه دستی نصب می‌شود.
5. **Theme:** فعلاً هر قالبی را رد کنید؛ در فاز ۴ قالب بامرو نصب می‌شود.

> 💡 **توصیه:** ویزارد ووکامرس چند افزونهٔ جانبی (مثل Jetpack) پیشنهاد می‌دهد. آن‌ها را **نصب نکنید** — پروژه به‌صورت عمدی سبک و بدون وابستگی طراحی شده است.

### گام ۳.۳ — نصب افزونه‌های شخص‌ثالث موردنیاز
از **Plugins → Add New** این‌ها را نصب و فعال کنید (مطابق README):
- **YITH WooCommerce Wishlist** (لیست علاقه‌مندی)
- **WP Super Cache** یا **Autoptimize** (کش)
- **Rank Math SEO** (سئو) — اختیاری

> ⚠️ **اشتباه رایج:** نصب ده‌ها افزونهٔ اضافی. هر افزونهٔ اضافی سطح حمله را افزایش می‌دهد. فقط موارد ضروری را نصب کنید.

---

## فاز ۴ — استقرار لایهٔ بامرو (افزونه‌ها + قالب)

### گام ۴.۱ — آپلود افزونه‌ها
1. با File Manager وارد `wp-content/plugins/` شوید.
2. پوشه‌های زیر را از `bamero-overlay.zip` آپلود و استخراج کنید:
   - `bamero-production-core`
   - `bamero-mobile-auth`
   - `bamero-zarinpal-gateway`
   - `bamero-woocommerce-setup`
   - `bamero-custom-plugin`
   - `bamero-essential-plugins`
3. مطمئن شوید هر پوشه فایل اصلی `.php` خودش را دارد.

### گام ۴.۲ — آپلود قالب
1. با File Manager وارد `wp-content/themes/` شوید.
2. پوشهٔ `bamero` را از بسته آپلود و استخراج کنید.

### گام ۴.۳ — جایگزینی `wp-config.php` و `.htaccess`
1. فایل `wp-config.php` فعلی (که وردپرس ساخته) را **rename** کنید به `wp-config.php.backup`.
2. فایل `wp-config.php` بامرو را از بسته در ریشه آپلود کنید.
3. فایل `.htaccess` بامرو را در ریشه آپلود کنید (نسخهٔ قبلی را backup کنید).
4. فایل `robots.txt` را هم جایگزین کنید.

> ⚠️ **نک
تهٔ حیاتی:** از این لحظه، سایت با خطای `Configuration error: missing required environment variable` بالا نمی‌آید، چون `wp-config.php` بامرو **fail-closed** است. این طبیعی است — در فاز ۵ متغیرها را تنظیم می‌کنیم.

> 💡 **توصیه:** فایل `wp-config.php` بامرو هیچ رمزی داخل خود ندارد. رمزها از متغیرهای محیطی خوانده می‌شوند. این یعنی حتی اگر فایل لو برود، رمزی افشا نمی‌شود.

---

## فاز ۵ — پیکربندی متغیرهای محیطی (اسرار)

این **حساس‌ترین فاز** است. `wp-config.php` بامرو تمام اسرار را از محیط می‌خواند.

### گام ۵.۱ — تولید نمک‌های وردپرس
1. در مرورگر بروید به: `https://api.wordpress.org/secret-key/1.1/salt/`
2. ۸ خط خروجی را کپی کنید (مقدارهای `AUTH_KEY` تا `NONCE_SALT`).

### گام ۵.۲ — تولید رمز وب‌هوک پرداخت
در ترمینال تایپ کنید:
```bash
openssl rand -hex 32
```
خروجی را برای `BAMERO_PAYMENT_WEBHOOK_SECRET` نگه دارید.

### گام ۵.۳ — روش تنظیم متغیرها (بسته به هاست)

**روش الف — cPanel (اکثر هاست‌های ایرانی):**
1. در cPanel به **MultiPHP INI Editor** → **Editor** بروید.
2. پایین صفحه، بخش **Environment Variables** (یا از طریق `.htaccess`).
3. اگر پنل از `.htaccess` پشتیبانی می‌کند، در **ابتدای** `.htaccess` اضافه کنید:
```apache
SetEnv DB_NAME "نام‌کاربری_bamero_shop"
SetEnv DB_USER "نام‌کاربری_bamero_user"
SetEnv DB_PASSWORD "رمز-قوی-دیتابیس"
SetEnv DB_HOST "localhost"
SetEnv TABLE_PREFIX "wp_bamero_"
```
> ⚠️ **هشدار امنیتی:** `SetEnv` مقادیر را در `.htaccess` **متن‌آشکار** ذخیره می‌کند. اگر هاست شما از متغیر محیطی واقعی (Panel Environment Variables) پشتیبانی می‌کند، **آن را ترجیح دهید**.

**روش ب — متغیر محیطی واقعی (هاست‌های حرفه‌ای/CloudLinux):**
1. در cPanel به بخش **Environment Variables** یا از طریق SSH به `~/.bashrc` اضافه کنید:
```bash
export DB_NAME="نام‌کاربری_bamero_shop"
export DB_USER="نام‌کاربری_bamero_user"
export DB_PASSWORD="رمز-قوی-دیتابیس"
export DB_HOST="localhost"
```
> ❌ **اشتباه رایج:** گذاشتن رمزها داخل `wp-config.php`. این کار دقیقاً همان چیزی است که معماری fail-closed بامرو از آن پرهیز می‌کند.

### گام ۵.۴ — فهرست کامل متغیرهای لازم
این‌ها **هم
ه** باید تنظیم شوند (از `.env.example`):

```ini
# دیتابیس
DB_NAME=
DB_USER=
DB_PASSWORD=
DB_HOST=localhost
TABLE_PREFIX=wp_bamero_

# نمک‌های وردپرس (۸ عدد)
AUTH_KEY=
SECURE_AUTH_KEY=
LOGGED_IN_KEY=
NONCE_KEY=
AUTH_SALT=
SECURE_AUTH_SALT=
LOGGED_IN_SALT=
NONCE_SALT=

# پرچم‌های runtime
WP_ENVIRONMENT_TYPE=production
WP_DEBUG=0
WP_DEBUG_LOG=0
DISALLOW_FILE_MODS=1

# نمک شناسهٔ داخلی مشتری (اجباری)
BAMERO_INTERNAL_ID_SALT=

# SMS.ir
SMS_PROVIDER=sms_ir
SMS_TIMEOUT=5
SMS_IR_API_BASE_URL=https://api.sms.ir/v1
SMS_IR_API_KEY=
SMS_IR_OTP_PARAMETER=Code
SMS_IR_ORDER_PARAMETER=OrderId
SMS_IR_TEMPLATE_LOGIN_OTP=
SMS_IR_TEMPLATE_ORDER_PROCESSING=
SMS_IR_TEMPLATE_ORDER_DELIVERED=
SMS_IR_TEMPLATE_ORDER_CANCELLED=
SMS_IR_TEMPLATE_PAYMENT_FAILED=
SMS_IR_TEMPLATE_REFUND_COMPLETED=

# زرین‌پال
ZARINPAL_API_BASE_URL=https://payment.zarinpal.com/pg/v4
ZARINPAL_STARTPAY_URL=https://payment.zarinpal.com/pg/StartPay
ZARINPAL_MERCHANT_ID=
ZARINPAL_CURRENCY=IRT
BAMERO_PAYMENT_WEBHOOK_SECRET=
```
برای `BAMERO_INTERNAL_ID_SALT` یک مقدار تصادفی دیگر بسازید:
```bash
openssl rand -hex 16
```

### گام ۵.۵ — تست بارگذاری
1. در مرورگر بروید به `https://rang.chasb.bamero.ir/`
2. اگر صفحه بالا آمد → متغیرها درست است.
3. اگر خطای `missing required environment variable: DB_NAME` دیدید → آن متغیر را تنظیم نکرده‌اید.
4. اگر خطای `rejected placeholder/insecure value` دیدید → مقدار را روی `changeme`/`secret`/`password` گذاشته‌اید؛ عوضش کنید.

---

## فاز ۶ — درون‌ریزی دیتابیس

### گام ۶.۱ — دریافت فایل دیتابیس
> ⚠️ **توجه:** این مخزن فایل `.sql` ندارد. دیتابیس واقعی (محصولات، برگه‌ها، تنظیمات) باید از منبع پروژه (backup قبلی) تأمین شود. اگر ندارید، باید محتوا را دستی بسازید (فاز ۸).

### گام ۶.۲ — درون‌ریزی (اگر فایل دارید)
1. در cPanel به **phpMyAdmin** بروید.
2. دیتابیس `bamero_shop` را از سمت چپ انتخاب کنید.
3. تب **Import** را باز کنید.
4. فایل `.sql` را **Choose File** کنید.
5. دقت کنید charset روی `utf8mb4` باشد.
6. **Go** را کلیک کنید.

### گام ۶.۳ — اصلاح پیشوند جدول
اگر فایل `.sql` با پیشوند `w
p_` است اما `wp-config.php` شما `wp_bamero_` می‌خواهد:
1. در phpMyAdmin، تب **SQL** را باز کنید و اجرا کنید:
```sql
-- نمونه: تغییر پیشوند (نام جدول‌ها را مطابق دیتابیس خود تنظیم کنید)
RENAME TABLE wp_posts TO wp_bamero_posts;
```
> 💡 **توصیه:** بهتر است فایل `.sql` را پیش از import با یک ابزار مثل `sed` اصلاح کنید:
```bash
sed 's/`wp_/`wp_bamero_/g' backup.sql > backup-fixed.sql
```

### گام ۶.۴ — اصلاح URLها
اگر دیتابیس از دامنهٔ دیگری آمده، URLها را عوض کنید:
```sql
UPDATE wp_bamero_options SET option_value = 'https://rang.chasb.bamero.ir' WHERE option_name IN ('siteurl','home');
```
> ⚠️ **اشتباه رایج:** فراموش‌کردن اصلاح URLها → ریدایرکت‌های اشتباه و صفحهٔ سفید.

---

## فاز ۷ — فعال‌سازی افزونه‌ها و قالب

### گام ۷.۱ — فعال‌سازی افزونه‌ها (ترتیب مهم است)
1. در پیشخوان به **Plugins** بروید.
2. **به‌ترتیب زیر** فعال کنید:
   1. `Bamero Production Core` ← **اول** (هستهٔ امنیتی و توابع پایه)
   2. `Bamero WooCommerce Setup`
   3. `Bamero Mobile Auth`
   4. `Bamero Zarinpal Gateway`
   5. `Bamero Custom Plugin`
   6. `Bamero Essential Plugins`
> 💡 **توصیه:** `bamero-production-core` باید **اول** فعال شود چون توابع کانونیک (`bamero_csp_nonce`, `bamero_security_headers`) را تعریف می‌کند.

### گام ۷.۲ — فعال‌سازی قالب
1. به **Appearance → Themes** بروید.
2. قالب **Bamero** را پیدا کنید.
3. **Activate** را کلیک کنید.

### گام ۷.۳ — تست پس از فعال‌سازی
1. سایت را رفرش کنید.
2. اگر خطای `Cannot redeclare` دیدید → یعنی نسخهٔ اصلاح‌شده (PR #3) را آپلود نکرده‌اید. فایل `functions.php` قالب را بررسی کنید که گارد `function_exists` دارد.

---

## فاز ۸ — تنظیمات وردپرس و ووکامرس

### گام ۸.۱ — تنظیمات عمومی وردپرس
1. **Settings → General:**
   - Site Title: بامرو
   - Tagline: فروش تخصصی رنگ، چسب و پوشش
   - WordPress Address (URL): `https://rang.chasb.bamero.ir`
   - Site Address (URL): `https://rang.chasb.bamero.ir`
   - Timezone: **Tehran**
   - Language: **فارسی**
2. **Save Changes**.

### گام ۸.۲ — پیوندهای یکتا (Permalinks)
1. **Settings → Permalinks.**
2. گزینهٔ **Post 
name** را انتخاب کنید.
3. **Save Changes** (این کار rewrite rules را flush می‌کند).

### گام ۸.۳ — تنظیمات ووکامرس
1. **WooCommerce → Settings → General:**
   - Store Address: آدرس فروشگاه
   - Currency: تومان (IRT)
2. **WooCommerce → Settings → Products:** وزن/ابعاد را تنظیم کنید.
3. **WooCommerce → Settings → Shipping:** مناطق ارسال (پست، تیپاکس، تحویل در محل).

### گام ۸.۴ — ساخت برگه‌های ضروری
مطابق مستندات، این برگه‌ها باید وجود داشته باشند:
- `/shop/` (فروشگاه) — توسط ووکامرس ساخته می‌شود
- `/cart/` (سبد خرید)
- `/checkout/` (تسویه حساب)
- `/my-account/` (حساب من)
- `/contact/` (تماس با ما) — با قالب Contact
- `/about/` (درباره ما) — با قالب `about.php`
- `/consultation/` (مشاوره)
> ⚠️ **اشتباه رایج:** نبودن برگهٔ `contact` یا `about` → لینک‌های شکسته در منو و خطای 404.

### گام ۸.۵ — افزودن محصولات
1. **Products → Add New.**
2. نام، توضیح، قیمت، دسته‌بندی (`product_cat`)، تصویر شاخص را وارد کنید.
3. برای هر محصول، فیلدهای سفارشی بامرو (سطح، حجم، کاربرد) را پر کنید.
> 💡 **توصیه:** حداقل ۱۰ محصول منتشرشده بسازید تا صفحهٔ اصلی (front-page) که `limit=10` دارد، محتوا نمایش دهد.

---

## فاز ۹ — پیکربندی درگاه زرین‌پال

### گام ۹.۱ — دریافت Merchant ID
1. وارد پنل زرین‌پال شوید: `https://www.zarinpal.com/`
2. به بخش **درگاه‌ها** بروید.
3. یک درگاه بسازید و **Merchant ID** (۳۶ کاراکتر) را کپی کنید.

### گام ۹.۲ — تنظیم متغیرها
```ini
ZARINPAL_MERCHANT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
ZARINPAL_CURRENCY=IRT
BAMERO_PAYMENT_WEBHOOK_SECRET=رمز-تولیدشده-در-گام-۵.۲
```

### گام ۹.۳ — فعال‌سازی درگاه در ووکامرس
1. **WooCommerce → Settings → Payments.**
2. درگاه **Zarinpal (Bamero)** را پیدا کنید.
3. **Enable** کنید.
4. **Manage** → عنوان و توضیح را تنظیم کنید → **Save**.

### گام ۹.۴ — تنظیم URL بازگشت (Callback)
در پنل زرین‌پال، آدرس بازگشت را تنظیم کنید:
```
https://rang.chasb.bamero.ir/?wc-api=bamero_zarinpal
```
> ⚠️ **اشتباه رایج:** تنظیم نادرست callback → پرداخت موفق اما سفارش «در انتظار پرداخت» می‌ماند.

### گام ۹.۵ — تست پرداخت (با درگاه تست)
1. یک محصول ارزا
ن بسازید.
2. به‌عنوان مشتری، خرید را کامل کنید.
3. در صفحهٔ زرین‌پال، پرداخت را انجام دهید.
4. بررسی کنید که سفارش به وضعیت **Processing/Completed** تغییر کند.
> 💡 **توصیه:** منطق درگاه، `is_paid()` را **اول از همه** چک می‌کند (ضد replay). اگر callback دوباره بیاید، سفارش دوباره پردازش نمی‌شود — این رفتار درست است.

---

## فاز ۱۰ — پیکربندی پیامک SMS.ir

### گام ۱۰.۱ — دریافت API Key
1. وارد پنل SMS.ir شوید: `https://sms.ir/`
2. به بخش **تنظیمات API** بروید.
3. **API Key** را کپی کنید.

### گام ۱۰.۲ — ساخت قالب‌های پیامک
در پنل SMS.ir، این قالب‌ها را بسازید و **Template ID** هرکدام را یادداشت کنید:
- قالب ورود (OTP) — با پارامتر `Code`
- قالب پردازش سفارش
- قالب تحویل سفارش
- قالب لغو سفارش
- قالب شکست پرداخت
- قالب بازگشت وجه

### گام ۱۰.۳ — تنظیم متغیرها
```ini
SMS_PROVIDER=sms_ir
SMS_IR_API_KEY=کلید-شما
SMS_IR_TEMPLATE_LOGIN_OTP=123456
SMS_IR_TEMPLATE_ORDER_PROCESSING=123457
# ... بقیه قالب‌ها
```

### گام ۱۰.۴ — تست OTP
1. از صفحهٔ ورود (my-account)، شمارهٔ موبایل خود را وارد کنید.
2. کد ۶ رقمی را دریافت کنید.
3. وارد شوید.
> 💡 **نکته:** کد OTP فقط **۱۲۰ ثانیه** اعتبار دارد، حداکثر **۳ بار** می‌توان امتحان کرد، و rate-limit **۵ بار در ساعت** است. این‌ها عمدی و امنیتی هستند.

---

## فاز ۱۱ — Redis و کش

### گام ۱۱.۱ — نصب افزونهٔ Redis
1. **Plugins → Add New** → جستجو: `Redis Object Cache`.
2. نصب و فعال کنید.

### گام ۱۱.۲ — پیکربندی اتصال
1. **Settings → Redis.**
2. روی **Enable Object Cache** کلیک کنید.
3. اگر Redis روی هاست فعال است، باید پیام **Connected** ببینید.
> ⚠️ **اشتباه رایج:** فعال‌کردن Redis بدون نصب سرویس روی هاست → خطای اتصال و کندی. ابتدا از پشتیبانی هاست بخواهید Redis را فعال کند.

### گام ۱۱.۳ — کش صفحه
1. **WP Super Cache** را فعال کنید.
2. **Settings → WP Super Cache → Easy → Caching On.**
> 💡 **نکتهٔ مهم:** کد بامرو صفحات حساس (سبد، تسویه، حساب من) را با `nocache_headers()` و `DONOTCACHEPAGE` از کش **مستثنی** می‌کند. این رفتار امنیتی است و نباید دستی تغییر کند.

---

## فاز ۱۲ — SSL و HTTPS

### گام ۱۲.۱ — نصب گواهی SSL
1. در cPanel به **
SSL/TLS Status** بروید.
2. دامنه را انتخاب و **Run AutoSSL** را کلیک کنید (Let's Encrypt رایگان).
3. صبر کنید تا گواهی صادر شود.

### گام ۱۲.۲ — اجبار HTTPS
1. **Settings → General** → هر دو URL را با `https://` تنظیم کنید.
2. فایل `.htaccess` بامرو خودش ریدایرکت ۳۰۱ به HTTPS و HSTS را اعمال می‌کند.
3. تست کنید: `http://rang.chasb.bamero.ir` باید به `https://` ریدایرکت شود.

### گام ۱۲.۳ — بررسی هدرهای امنیتی
با این دستور بررسی کنید:
```bash
curl -I https://rang.chasb.bamero.ir/
```
باید این هدرها را ببینید:
```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Content-Security-Policy: default-src 'self'; ...
```
> 💡 **توصیه:** سایت را در `https://securityheaders.com` تست کنید — هدف نمرهٔ **A** یا بالاتر است.

---

## فاز ۱۳ — تست‌های نهایی (Smoke Test)

### گام ۱۳.۱ — چک‌لیست تست صفحه‌به‌صفحه
| صفحه | URL | چه چیزی را چک کنیم |
|------|-----|-------------------|
| خانه | `/` | نمایش محصولات، اسلایدر، لینک‌ها |
| فروشگاه | `/shop/` | فیلترها، دسته‌بندی‌ها |
| محصول | `/product/...` | تصویر، قیمت، افزودن به سبد |
| سبد خرید | `/cart/` | به‌روزرسانی تعداد، حذف |
| تسویه | `/checkout/` | فرم، انتخاب درگاه |
| حساب من | `/my-account/` | ورود OTP |
| تماس | `/contact/` | فرم تماس |
| درباره | `/about/` | محتوا |

### گام ۱۳.۲ — تست موبایل و RTL
1. با Chrome DevTools (F12) → حالت موبایل (۳۲۰px تا ۱۴۴۰px).
2. بررسی کنید که همه‌چیز RTL و راست‌چین باشد.
3. روی دستگاه واقعی هم تست کنید.

### گام ۱۳.۳ — تست عملکرد
1. سایت را در `https://pagespeed.web.dev/` تست کنید.
2. هدف: Performance بالای ۸۰ در موبایل.
3. بررسی کنید کش فعال باشد.

### گام ۱۳.۴ — تست امنیت
1. `https://securityheaders.com` → نمرهٔ A.
2. `https://www.ssllabs.com/ssltest/` → نمرهٔ A.
3. بررسی کنید `xmlrpc.php` مسدود باشد: `curl -I https://rang.chasb.bamero.ir/xmlrpc.php` → باید `403` بدهد.

### گام ۱۳.۵ — اجرای گیت استاتیک (اختیاری، روی هاست با SSH)
```bash
cd /path/to/site
bash tests/production_gate.sh
```
باید `GATE RESULT: PASS
` ببینید.

---

## فاز ۱۴ — Go-Live و پایش

### گام ۱۴.۱ — بکاپ کامل پیش از اعلام عمومی
1. در cPanel → **Backup** → **Download a Full Account Backup**.
2. فایل دیتابیس را جدا هم export کنید (phpMyAdmin → Export).

### گام ۱۴.۲ — حذف فایل‌های نصب
1. فایل `wp-config.php.backup` را حذف کنید.
2. هر فایل `.zip` یا `.tar.gz` باقی‌مانده در ریشه را حذف کنید.
> ⚠️ **اشتباه رایج:** باقی‌گذاشتن `bamero-overlay.zip` روی هاست → افشای سورس کد.

### گام ۱۴.۳ — پایش
- **Uptime:** یک سرویس مثل UptimeRobot روی `/bamero/v1/health/live` تنظیم کنید.
- **Health endpoints:**
  - Liveness: `https://rang.chasb.bamero.ir/wp-json/bamero/v1/health/live`
  - Readiness: `https://rang.chasb.bamero.ir/wp-json/bamero/v1/health/ready`
- **لاگ‌ها:** `wp-content/debug.log` (اگر `WP_DEBUG_LOG=1`).
- **شاخص‌ها:** نرخ تبدیل، درآمد/ساعت، نرخ موفقیت پرداخت، نرخ تحویل پیامک.

### گام ۱۴.۴ — برنامهٔ بکاپ خودکار
1. در cPanel → **Backup Wizard** یا افزونهٔ `UpdraftPlus`.
2. بکاپ روزانهٔ دیتابیس + هفتگی فایل‌ها.

---

## فاز ۱۵ — Rollback (بازگشت)

اگر پس از Go-Live مشکلی پیش آمد:
1. قالب و افزونه‌های تازه را **Deactivate** کنید.
2. `wp-config.php` و `.htaccess` قبلی را از backup برگردانید.
3. دیتابیس را از آخرین export بازگردانید.
4. کش‌ها را purge کنید.
5. سفارش‌های ایجادشده در بازهٔ مشکل را با گزارش callback زرین‌پال تطبیق دهید.
> ⚠️ **هرگز داده حذف نکنید.** فقط rollback کنید.

---

## پیوست الف — اشتباهات رایج و راه‌حل

| # | اشتباه | پیامد | راه‌حل |
|---|--------|-------|--------|
| ۱ | استقرار بدون رفع نقص مرگ‌آور (PR #3) | `Fatal error: Cannot redeclare` | ابتدا PR #3 را merge و فایل اصلاح‌شده را آپلود کنید |
| ۲ | آپلود پوشهٔ `.git/` | افشای سورس و تاریخچه | هرگز `.git` را آپلود نکنید |
| ۳ | گذاشتن رمزها داخل `wp-config.php` | افشا در صورت نفوذ | از متغیر محیطی استفاده کنید |
| ۴ | `display_errors = On` | افشای مسیر فایل‌ها | `display_errors = Off` |
| ۵ | نام کاربری `admin` | هدف brute-force | نام کاربری سفارشی |
| ۶ | نصب وردپرس بعد از لایهٔ بامرو | تداخل و خطا | اول وردپرس، بعد لایه |
| ۷ | فعال‌سا
زی افزونه‌ها با ترتیب اشتباه | خطای undefined function | `production-core` اول |
| ۸ | فراموشی flush permalinks | خطای 404 همه‌جا | Settings → Permalinks → Save |
| ۹ | callback زرین‌پال اشتباه | سفارش «در انتظار پرداخت» | آدرس `?wc-api=bamero_zarinpal` |
| ۱۰ | Redis بدون نصب سرویس | خطای اتصال | ابتدا سرویس را فعال کنید |
| ۱۱ | باقی‌گذاشتن فایل zip | افشای سورس | فایل‌های موقت را حذف کنید |
| ۱۲ | تنظیم نکردن `BAMERO_INTERNAL_ID_SALT` | خطای ثبت‌نام مشتری | یک مقدار تصادفی تنظیم کنید |
| ۱۳ | عدم بکاپ پیش از Go-Live | عدم امکان rollback | بکاپ کامل بگیرید |
| ۱۴ | نبودن برگه‌های `contact`/`about` | لینک شکسته 404 | برگه‌ها را بسازید |
| ۱۵ | کمتر از ۱۰ محصول | صفحهٔ اصلی خالی | حداقل ۱۰ محصول منتشر کنید |

---

## پیوست ب — توصیه‌های حرفه‌ای

1. **محیط staging جداگانه بسازید.** هرگز مستقیم روی پروداکشن تست نکنید. (خودِ اسناد پروژه اعلام کرده‌اند که تست staging هرگز انجام نشده — این را جبران کنید.)
2. **نسخه‌ها را pin کنید.** وردپرس و ووکامرس را روی نسخهٔ ثابت نگه دارید و آپدیت خودکار را غیرفعال کنید.
3. **CI را فعال نگه دارید.** پس از merge PR #3، ورک‌فلوی `production-gate.yml` روی هر push اجرا می‌شود و از بازگشت باگ‌ها جلوگیری می‌کند.
4. **اسرار را در Secret Manager نگه دارید،** نه در فایل. در صورت امکان از متغیرهای محیطی واقعی پنل استفاده کنید.
5. **مانیتورینگ را از روز اول فعال کنید.** health endpoints آمادهٔ اتصال به UptimeRobot هستند.
6. **بکاپ خودکار روزانه** دیتابیس و هفتگی فایل‌ها را فراموش نکنید.
7. **پس از هر تغییر، گیت را اجرا کنید:** `bash tests/production_gate.sh`.
8. **لاگ‌ها را پایش کنید** با correlation ID (کد بامرو `X-Request-ID` تولید می‌کند).
9. **دسترسی‌ها را محدود کنید:** `DISALLOW_FILE_MODS=1` و `DISALLOW_FILE_EDIT=true` در `wp-config.php` فعال هستند — آن‌ها را غیرفعال نکنید.
10. **پس از استقرار، PR #3 را به `main` merge کنید** تا شاخهٔ اصلی تمیز و آمادهٔ استقرارهای بعدی باشد.

---

## خلاصهٔ ترتیب اجرا (چک‌لیست سریع)

```
[ ] ۱. merge کردن PR #3 در GitHub
[ ] ۲. تنظیم PHP 8.3 + افزونه‌ها روی هاست
[ ] ۳. ساخت دیتابیس
[ ] ۴. نصب هستهٔ WordPre
ss
[ ] ۵. نصب WooCommerce
[ ] ۶. نصب افزونه‌های شخص‌ثالث (YITH, Cache)
[ ] ۷. آپلود افزونه‌های بامرو + قالب
[ ] ۸. جایگزینی wp-config.php + .htaccess + robots.txt
[ ] ۹. تنظیم متغیرهای محیطی (اسرار)
[ ] ۱۰. درون‌ریزی دیتابیس + اصلاح URL
[ ] ۱۱. فعال‌سازی افزونه‌ها (production-core اول) + قالب
[ ] ۱۲. تنظیمات وردپرس/ووکامرس + ساخت برگه‌ها + محصولات
[ ] ۱۳. پیکربندی زرین‌پال + تست پرداخت
[ ] ۱۴. پیکربندی SMS.ir + تست OTP
[ ] ۱۵. Redis + کش
[ ] ۱۶. SSL + اجبار HTTPS
[ ] ۱۷. Smoke Test کامل
[ ] ۱۸. بکاپ + Go-Live + پایش
```
