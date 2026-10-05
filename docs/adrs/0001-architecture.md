# ADR-0001: معماری فروشگاه

## تصمیم
تم کلاسیک فعلی حفظ می‌شود و منطق کسب‌وکار در افزونه‌های اختصاصی همین بسته (هستهٔ `bamero-production-core` و همراهان آن: `bamero-mobile-auth`، `bamero-zarinpal-gateway`، `bamero-woocommerce-setup`) قرار می‌گیرد؛ قالب فقط از هوک‌ها و APIهای WooCommerce استفاده می‌کند.

## دلیل
کاهش coupling، امکان تعویض تم، سازگاری با WooCommerce 9.x و رعایت invariant عدم تغییر هسته WordPress.

## پیامد
به‌روزرسانی‌های WooCommerce باید با تست قالب‌ها و قرارداد `BAMERO_CART_SELECTOR` همراه باشد.

# ADR-0002: کش و transient

از transient فقط برای حالت‌های کوتاه‌عمر استفاده می‌شود: نگهداری OTP (با TTL چنددقیقه‌ای)، قفل موقت و شمارندهٔ محدودیت نرخ ورود (پنجرهٔ یک‌ساعته در `bamero-mobile-auth` و `bamero-production-core`). هیچ کش نتیجهٔ پرس‌وجوی محصولات در کد وجود ندارد. Object cache واقعی Redis باید توسط میزبان فعال شود؛ بدون آن، transientها در پایگاه‌داده ذخیره می‌شوند و فقط fallback محسوب می‌شوند.

# ADR-0003: انتخاب افزونه

افزونه‌های third-party فقط از WordPress.org یا vendor رسمی، با نگهداری فعال و بدون CVE حل‌نشده high/critical انتخاب می‌شوند. درگاه زرین‌پال به‌صورت افزونهٔ اختصاصی همین بسته (`bamero-zarinpal-gateway`) ارائه می‌شود و عمداً در فهرست نصب افزونه‌های third-party قرار نمی‌گیرد؛ افزونه‌های آن فهرست فقط با تأیید جداگانهٔ مدیر نصب می‌شوند.

# ADR-0004: استقرار

TLS termination، PHP 8.3، MySQL سازگار با WordPress، Redis، secret injection محیطی و WAF در لایه میزبان توصیه می‌شود. `wp-config.php` فقط env را مصرف می‌کند و نصب/ویرایش افزونه در production بسته است.