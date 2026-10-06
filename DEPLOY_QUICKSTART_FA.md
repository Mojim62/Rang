# 🚀 استقرار سریع بامرو — ۳۰ دقیقه

> نسخهٔ فشردهٔ [`GO_LIVE_PHP_HOSTING_FA.md`](GO_LIVE_PHP_HOSTING_FA.md). اگر عجله دارید فقط همین صفحه را دنبال کنید.

## راه سریع‌تر: بستهٔ آماده از GitHub (بدون نصب git)

کامیت‌های تگ‌شدهٔ `v*` به‌صورت خودکار در GitHub Actions بستهٔ استقرار تمیز (`wp-config.php` + `wp-content`) می‌سازند:
1. بخش **Actions → Build deploy package** مخزن را باز کنید.
2. آخرین اجرا را باز کنید و از پایین صفحه، **Artifact** `bamero-deploy` را دانلود کنید.
3. محتویات zip را در `public_html` اکسترکت کنید. تمام — نیاز به git یا اسکریپت محلی نیست.

## مسیر استاندارد (با git)

```bash
git clone https://github.com/Mojig62m/Rang.git
cd Rang
bash scripts/build-deploy.sh   # → dist/bamero-deploy-<date>.zip
```

## چک‌لیست ۳۰ دقیقه‌ای

| ⏱️ | گام | کجا |
|----|-----|-----|
| ۱۰ دقیقه | خرید هاست PHP 8.3+ با SSL + اتصال NS دامنه | پنل هاست / فروشندهٔ دامنه |
| ۵ دقیقه | ساخت دیتابیس: **MySQL Database Wizard** (نام، کاربر، رمز) | cPanel |
| ۵ دقیقه | دانلود بستهٔ آماده (بالا) و اکسترکت در `public_html` | cPanel → File Manager |
| ۵ دقیقه | ساخت `.env` (از `.env.example`): دیتابیس + [۸ کلید salt](https://api.wordpress.org/secret-key/1.1/salt/) + `ZARINPAL_CURRENCY=IRR` | یک پوشه بالاتر از `public_html` |
| ۵ دقیقه | `https://دامنه/wp-admin/install.php` — نام کاربری مدیر **admin نباشد** | مرورگر |
| ۲ دقیقه | فعال‌سازی: تم بامرو + ۶ افزونهٔ بامرو + نصب WooCommerce | پیشخوان |

## اثبات go-live (۲ دقیقه — رد نکنید)

```bash
wp eval-file public_html/tests/staging_smoke.php
```
باید بگوید: «همهٔ بررسی‌ها موفق — محیط آمادهٔ go-live است.»

سپس یک خرید واقعی با مبلغ کم (قلم‌مو ۶۴,۵۰۰ ریال) تا verify زرین‌پال و پیامک OTP اثبات شود.

## چیزهایی که می‌توانند صبر کنند

Wordfence، WP Super Cache، Rank Math، اینماد، Search Console — هفتهٔ اول بعد از استقرار.
