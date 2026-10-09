# گزارش ممیزی و پاکسازی UI/UX — آماده‌سازی Go-Live
**مخزن:** Mojig62m/Rang (پوسته بامرو — فروشگاه رنگ و چسب، RTL فارسی، ووکامرس)
**شاخه کاری:** `fix/ui-ux-golive-audit-2026-10` (ایجادشده از main در کامیت b490fe9)
**تاریخ:** ۲۰۲۶-۱۰-۰۹
**دامنه:** تحلیل کامل UI/UX تمام قالب‌های پوسته، شناسایی کد کثیف/مرده/موقتی/منسوخ، رفع باگ‌های ریسپانسیو و دسترسی‌پذیری، سپس کامیت، PR و CI سبز.

---

## ۱. خلاصه مدیریتی

پس از بازبینی فایل‌به‌فایل تمام قالب‌های پوسته (`header.php`, `footer.php`, `functions.php`, `style.css`, `js/main.js`, `css/woocommerce.css`, `woocommerce/*.php`, `page.php`, `index.php`, `front-page.php`, `searchform.php` و غیره)، موارد زیر اثبات شد و اصلاح گردید:

1. **باگ بحرانی ریسپانسیو RTL:** منوی کشویی موبایل در حالت بسته روی صفحه قابل مشاهده بود (خطای جهت transform در RTL).
2. **کد JS مرده و مخرب:** حدود دو سوم `js/main.js` شامل انتخاب‌گرهای بدون CSS، فراخوانی AJAX با endpoint و nonce اشتباه، اسلایدر jQuery UI هرگز بارگذاری‌نشده، مودال تصویر که کلیک روی کارت محصول را می‌بلوکید، و resize/DOM mutation که HTML را خراب می‌کرد.
3. **دو عرض تکراری برای جستجو و دو لینک پرش به محتوا** در هدر (یکی بدون CSS = نمایش متن خام در صفحه).
4. **تابع breadcrumb مرده** در `functions.php` که هرگز hook نمی‌شد؛ در نتیجه breadcrumb پیش‌فرض انگلیسی ووکامرس چاپ می‌شد.
5. **نشانگر سبد خرید کهنه (stale)** روی صفحات فروشگاه/محصول (اسکریپت wc-cart-fragments فقط در cart/checkout صف بود).
6. **لایت‌باکس native ووکامرس غیرفعال** شده بود (CSS مخفی‌کننده `woocommerce-product-gallery__trigger`).
7. **قابلیت دسترسی (a11y):** لینک پرش به محتوا در فوکوس کیبورد ظاهر نمی‌شد؛ آیکن ایموجی 💬 به‌جای SVG سازگار با بقیه سیستم آیکون.
8. **قواعد CSS مرده:** `.filter-chips`, `.top-bar-contact .top-email`, قواعد `.icon-btn` (بعد از حذف عنصر از هدر) و مدخل `.icon-btn` در فهرست touch-target.

تمام موارد فوق در پنج کامیت اثبات‌شده روی شاخه کاری رفع شد (بخش ۳).

---

## ۲. یافته‌های اثبات‌شده (با محل دقیق)

| # | فایل / محل | یافته | دسته | شدت |
|---|---|---|---|---|
| F-01 | `style.css` قاعده `.main-navigation` (media ≤767px) | `inset-inline-end: 0` در RTL یعنی لبه چپ + `transform: translateX(110%)` یعنی جابه‌جایی به راست → در حالت «بسته» منو روی صفحه دیده می‌شد | باگ ریسپانسیو | بحرانی |
| F-02 | `js/main.js` | تزریق عنصر `.scroll-to-top` بدون هیچ قاعده CSS (عنصر نامرئی و مرده) + انتخاب‌گرهای بدون CSS: `.header-main-wrap`, `.filter-chips`, `.skip-to-content`, `.mobile-tabs`, `.keyboard-focus`, `.woocommerce-filters` | کد مرده | بالا |
| F-03 | `js/main.js` | init اسلایدر jQuery UI (`$.fn.slider`) — کتابچه بارگذاری نمی‌شود؛ ویجت قیمت ووکامرس اسکریپت خودش را دارد | کد منسوخ/شکسته | بالا |
| F-04 | `js/main.js` | AJAX fragments با endpoint/nonce نادرست (مالک واقعی: `wc-cart-fragments` هسته ووکامرس) | کد صوری/شکسته | بالا |
| F-05 | `js/main.js` | مودال تصویر سفارشی که کلیک روی لینک کارت محصول را می‌بلوکید؛ read-more/less که HTML توضیحات را تخریب می‌کرد؛ اعتبارسنجی فرم JS موازی با اعتبارسنجی native + ووکامرس (کلاس `.error` بدون CSS) | کد مخرب | بالا |
| F-06 | `js/main.js` | lazy-load JS موازی با هسته وردپرس؛ رنگ‌آمیزی JS `.stock-status` و `.product-color-code` (باید CSS باشد)؛ جهش `role="button"` روی DOM | الگوی ضداستاندارد | متوسط |
| F-07 | `header.php` | دکمه/لینک تکراری جستجو (`.icon-btn`) هم‌زمان با فرم جستجوی فعال → دو عرض هم‌هدف | UI تکراری | متوسط |
| F-08 | `header.php` + `functions.php` | دو لینک «پرش به محتوا»: یکی hardcode در قالب، یکی از hook `wp_body_open`؛ نسخه بدون CSS متن خام قابل‌مشاهده روی صفحه بود | باگ a11y/UI | بالا |
| F-09 | `functions.php` | تابع `bamero_woocommerce_breadcrumb()` هرگز به هیچ hookی وصل نبود (کاملاً مرده) → breadcrumb پیش‌فرض انگلیسی چاپ می‌شد | کد مرده | بالا |
| F-10 | `functions.php` | `wc-cart-fragments` فقط در cart/checkout صف می‌شد → badge تعداد سبد در shop/product بعد از افزودن به سبد به‌روز نمی‌شد | باگ | بالا |
| F-11 | `css/woocommerce.css` | `display:none` روی `.woocommerce-product-gallery__trigger` → لایت‌باکس native تصویر محصول غیرفعال | کد صوری | متوسط |
| F-12 | `woocommerce/archive-product.php` | ایموجی 💬 به‌جای SVG در دکمه شناور واتساپ (ناسازگار با سیستم آیکون SVG سراسری) | ناسازگاری بصری | کم |
| F-13 | `style.css` | قواعد مرده: `.filter-chips` (با عدم تطابق `.is-active` در CSS و `active` در JS)، `.top-email`، قواعد `.icon-btn` | کد مرده | متوسط |
| F-14 | `style.css` | لینک پرش به محتوا قاعده `:focus` نداشت (در فوکوس کیبورد آشکار نمی‌شد) | باگ a11y | بالا |
| F-15 | `footer.php` | یک سطر `<li>` با تورفتگی خراب (۳۲ فاصله) | کد کثیف | کم |
| F-16 | `style.css` | ارتفاع منوی موبایل `100vh` بدون جایگزین `100dvh` (پرش در iOS/Android با نوار ابزار مرورگر) | باگ ریسپانسیو | متوسط |

**بررسی و رد موارد مشکوک (شفافیت کامل):**
- `css/icons.css` حذف نشد: استفاده‌شده است (جداکننده breadcrumb، آیکون فرم جستجوی محصول، آیکون scroll-to-top).
- فایل‌های workflow مخزن (`deploy.yml`, `build-release.yml`, `image-compress.yml`) از طریق API بازبینی شدند و **UTF-8 سالم** هستند؛ «mojibake» مشاهده‌شده در بررسی قبلی، آرتیفکت نمایشی خط لوله bash محلی بود، نه خرابی واقعی فایل. هیچ کامیت «ترمیم» ساختگی برای آن انجام نشد.
- `deploy.yml` فقط `workflow_dispatch` است — عمدی و مستندشده در خود فایل (نیازمند environment/secrets که هنوز پیکربندی نشده). دست‌نخورده باقی ماند.

---

## ۳. اصلاحات انجام‌شده (کامیت‌به‌کامیت)

| کامیت | SHA | فایل‌ها | شرح |
|---|---|---|---|
| ۱ | `4903b41` | `js/main.js` | بازنویسی کامل: حذف تمام کد مرده/شکسته F-02..F-06؛ حفظ رفتارهای سالم: منوی کشویی موبایل، اسکرول به اولین خطای checkout، آکاردئون دسته‌ها، گارد عددی quantity، ماشین‌حساب پوشش‌دهی |
| ۲ | `f11b9e3` | `style.css` | F-01: `translateX(-110%)`؛ F-16: fallback `100dvh`؛ z-index هامبورگر 1004 + انیمیشن → ×؛ F-13: حذف قواعد مرده؛ F-14: بلوک فوکوس skip-link؛ افزودن CSS `.scroll-to-top` (RTL-safe)؛ `Version: 2.1.0` |
| ۳ | `28a6424` | `functions.php` | F-09: جایگزینی تابع مرده با فیلتر استاندارد `woocommerce_breadcrumb_defaults` (جداکننده chevron + aria-label «مسیر ناوبری» + خانه «خانه»)؛ F-10: صف `wc-cart-fragments` روی تمام سطوح تجاری؛ skip-link از طریق `wp_body_open` |
| ۴ | `cae86ae` | `header.php`, `footer.php`, `woocommerce/archive-product.php`, `css/woocommerce.css` | F-07: حذف `.icon-btn` تکراری؛ F-08: حذف skip-link هاردکد؛ F-11: حذف `display:none` (بازگرداندن لایت‌باکس native)؛ F-12: جایگزینی ایموجی با SVG واتساپ؛ F-15: اصلاح تورفتگی؛ افزودن کنترل سروررندر `.scroll-to-top` در فوتر |
| ۵ | این کامیت | `UI_UX_GOLIVE_AUDIT_2026-10.md` | همین گزارش |

---

## ۴. روش اعتبارسنجی

### ۴.۱ اثبات‌شده با CI قطعی (production-gate)
گردش‌کار `.github/workflows/production-gate.yml` روی pull_request اجرا می‌شود و شامل:
- `php -l` روی تمام فایل‌های PHP با PHP 8.3 (توان اعتبار نحوی کامیت‌های ۳، ۴ و ۵)
- `tests/production_gate.sh`: آشکارساز تابع تکراری (اثبات عدم تعریف مجدد توابع)، اسکن راز هاردکدشده، بررسی nonce روی بلوک‌های JSON-LD (توکن CSP دست‌نخورده)
- مانIFEST آرتیفکت ریلیز

### ۴.۲ اثبات‌شده با بازبینی پس-کامیت (برنامه‌ای، روی شاخه)
پس از کامیت ۴، وضعیت نهایی تمام فایل‌ها مجدداً خوانده و به‌صورت خودکار بررسی شد:
- `style.css`: `translateX(-110%)` موجود، `translateX(110%)` صفر مورد؛ `100dvh` موجود؛ `.icon-btn` و `filter-chips` صفر مورد؛ قواعد فوکوس skip-link و `.scroll-to-top` موجود؛ `Version: 2.1.0`
- `js/main.js`: هیچ `$.fn.slider`، AJAX fragments، مودال، read-more/less، اعتبارسنجی JS موازی؛ رفتارهای حفظ‌شده موجود
- `functions.php`: فیلتر breadcrumb هوک‌شده، تابع مرده حذف‌شده، `wc-cart-fragments` صف‌شده، `BAMERO_VERSION = 2.1.0`
- `header.php`: `.icon-btn` صفر مورد، یک skip-link (فقط از `wp_body_open`)
- `footer.php`: `.scroll-to-top` موجود، تورفتگی اصلاح‌شده
- `archive-product.php`: ایموجی صفر مورد، SVG موجود
- `css/woocommerce.css`: `__trigger` صفر مورد

### ۴.۳ صریحاً اثبات‌نشده (صادقانه — نیازمند استیجینگ/مرورگر)
این گزارش ادعای تأیید موارد زیر را **ندارد**، زیرا از این محیط به مرورگر/سرور runtime دسترسی وجود ندارد:
1. رندر بصری واقعی صفحات (تست چشمی طراحی نهایی در مرورگر)
2. امتیاز Lighthouse / Core Web Vitals میدانی
3. رفتار واقعی درگاه پرداخت و SMS
4. استقرار واقعی روی سرور (deployment دستی است و نیازمند environment/secrets مالک)

مستندات GO_LIVE مخزن نیز همین تفکیک را الزام می‌دارد. توصیه می‌شود قبل از انتشار نهایی، یک بار روی استیجینگ (یا Playground) صفحات اصلی — خانه، فروشگاه، محصول، سبد، checkout، حساب کاربری — در عرض 360px تا 1440px بازبینی چشمی شود.

---

## ۵. نتیجه‌گیری

تمام یافته‌های ممیزی با محل دقیق فایل اثبات و در پنج کامیت رفع شد؛ کد مرده و صوری حذف گردید؛ باگ بحرانی ریسپانسیو RTL، breadcrumb مرده، badge سبد کهنه، لایت‌باکس غیرفعال و دو نقص a11y اصلاح شد.

---

## ۶. تأیید نهایی Go-Live (اثبات‌شده، ۲۰۲۶-۱۰-۰۹)

| مرحله | شاهد | نتیجه |
|---|---|---|
| PR | [#31](https://github.com/Mojim62/Rang/pull/31) — «fix(theme): UI/UX go-live audit — RTL mobile drawer, dead-code removal, a11y & responsive hardening» | باز و مرج‌شده |
| بررسی CI روی PR | static-production-gate روی head commit `2dffbba` — [run 38004292878](https://github.com/Mojim62/Rang/actions/runs/38004292878/job/114069401620) | ✅ success |
| Merge به main | کامیت مرج `f9fc144` (+225 / −458 در ۸ فایل، ۲۰۲۶-۱۰-۰۹T23:25:15Z) | کامل |
| بررسی CI روی main | static-production-gate روی کامیت مرج — [run 38004334424](https://github.com/Mojim62/Rang/actions/runs/38004334424/job/114069528873) (شروع 23:25:21Z، پایان 23:25:39Z) | ✅ success |
| وضعیت ترکیبی کامیت مرج | Vercel: «Deployment has completed» | ✅ success |
|Annotations گیت | یک notice اطلاع‌رسانی درباره مهاجرت ubuntu-latest به Ubuntu 26 (از ۲۰۲۶-۱۰-۱۹) — بدون خطا | بدون اقدام |

**جمع‌بندی صادقانه:** زنجیره کامل PR → بررسی سبز → merge به main → بررسی سبز مجدد روی main → استقرار موفق، اثبات شده است. آنچه خارج از دسترسی این ممیزی باقی می‌ماند صرفاً موارد بخش ۴.۳ (رندر مرورگر، Lighthouse، پرداخت/SMS) است که به صراحت در همین گزارش ثبت شده و نیازمند استیجینگ واقعی مالک است.
