# سند اثبات سخت‌سازی نهایی تم بامرو برای Go-Live — اکتبر ۲۰۲۶

## مشخصات سند

| مورد | مقدار |
|---|---|
| شاخهٔ اجرا | `fix/theme-golive-hardening` (پایه: `2e41e3b`) |
| نسخهٔ تم | ۲.۱.۰ ← **۲.۲.۰** |
| دامنه | `wp-content/themes/bamero/` |
| تعداد کامیت | ۱۰ |
| مرجع ممیزی | [UI_UX_GOLIVE_AUDIT_2026-10.md](UI_UX_GOLIVE_AUDIT_2026-10.md) و تحلیل متقابل پنج سند Go-Live |
| گیت استاتیک | Bamero production gate (lint، آشکارساز توابع تکراری، اسکن راز، پوشش nonce در JSON-LD) |

## ۱. خلاصه اجرایی

پیوند ممیزی UI/UX (PR #31، تم ۲.۱.۰) و تحلیل متقابل پنج سند Go-Live، فهرستی از یافته‌های باقی‌ماندهٔ سطح تم را مشخص کرد. این شاخه تمام آن یافته‌ها را در ده کامیت اصلاح، اثبات و مستند می‌کند:

- رفع نقص‌های امنیتی اولویت یک (XSS فرم جستجو، حذف دارایی‌های باینری خراب).
- تکمیل اعتماد محلی‌سازی فارسی (ارقام فارسی در همهٔ نقاط نمایش عدد).
- بهبود Core Web Vitals (اولویت LCP، بارگذاری شرطی CSS و jQuery سطوح تجارت).
- اصلاح سئو (H1 داینامیک آرشیو، breadcrumb واقعی، حذف قالب override یک‌به‌یک هسته، 404 کاربردی).
- یک‌پارچه‌سازی معماری CSS (ادغام فایل، توکن واحد، نقاط شکست واحد، اهداف لمسی 44px).
- تفکیک ماژولار functions.php به هفت ماژول inc/ و بازنویسی vanilla-first فایل js/main.js.
- ارتقای نسخهٔ تم به ۲.۲.۰.

## ۲. روش‌شناسی اثبات

روش کار به‌گونه‌ای طراحی شد که هر تغییر قابل بازتولید و قابل رد باشد:

1. **جایگزینی رشته‌ای اثبات‌شده:** هر ویرایش با شمارش دقیق تعداد تطابق‌ها پیش از جایگزینی انجام شد؛ هیچ ویرایش بدون assertion اجرا نشد.
2. **حسابرسی فهرست توابع:** در تفکیک ماژولار، فهرست کامل توابع (۴۶ تابع با پیشوند bamero_) پیش و پس از تفکیک استخراج و برابری یک‌به‌یک اثبات شد. شمارش add_action و add_filter نیز پیش و پس بررسی شد؛ تنها تفاوت عامدانه، ادغام دو callback برای wp_revisions_to_keep در یک تابع بود.
3. **بالانس ساختاری:** تعادل آکولادها در هر فایل خروجی و وجود گارد ABSPATH در همهٔ ماژول‌ها بررسی شد.
4. **قراردادهای گیت تولید:** خروجی نهایی با منطق tests/production_gate.sh تطبیق داده شد — قرارداد GATE-03 (انتخابگر span.cart-count در فرگمنت سربرگ)، پوشش nonce روی تمام JSON-LDها، و تک‌تعریفی بودن توابع برای آشکارساز guard-aware.

## ۳. اصلاحات ردیف به ردیف

### ۳.۱ امنیت و یکپارچگی دارایی‌ها (P1)

| # | یافته | اصلاح انجام‌شده | فایل | کامیت |
|---|---|---|---|---|
| ۱ | مقدار value فرم جستجوی محصول بدون escaping (XSS بازتابی) | esc_attr(get_search_query()) | inc/woocommerce.php (سابقاً functions.php) | 861eb04، fccac7b |
| ۲ | favicon.png و logo.png در واقع JPEG های misnamed با حجم ۱۶۱ کیلوبایت بودند | حذف کامل؛ favicon.svg و logo.svg تنها دارایی‌های آیکون | images/ | bd76231، eddac35 |
| ۳ | کاوش آزمایشی آپلود باینری از طریق API | اثبات خرابی round-trip (۶۹۴۴ بایت ذخیره‌شده در برابر ۴۱۹۶ بایت مورد انتظار) و حذف فایل کاوش | — | 8b85d37، 4a3c1bb |
| ۴ | لوگوی JSON-LD به PNG حذف‌شده اشاره می‌کرد | ارجاع به logo.svg با ابعاد 512×512 | inc/seo.php | 861eb04، fccac7b |
| ۵ | تگ preload فونت هم در header.php و هم در تابع تم تکرار بود | حذف نسخهٔ سخت‌کدشده؛ مالک تنها bamero_preload_primary_font است | header.php، inc/setup.php | 861eb04 |
| ۶ | nonce های CSP و هدرهای امنیتی | انتقال بدون تغییر و حفظ گاردهای function_exists (مالک متعارف در افزونهٔ bamero-production-core است) | inc/security.php | fccac7b |

### ۳.۲ اعتماد محلی‌سازی فارسی

| # | یافته | اصلاح | کامیت |
|---|---|---|---|
| ۱ | شمارندهٔ سبد خرید در سربرگ با ارقام لاتین | bamero_persian_digits در header.php و فرگمنت سربرگ؛ انتخابگر span.cart-count مطابق قرارداد GATE-03 بدون تغییر ماند | 861eb04 |
| ۲ | قیمت‌های صفحهٔ اصلی با ارقام لاتین | قالب‌بندی از طریق bamero_format_price_html | 861eb04 |
| ۳ | برچسب «مشاهده سریع» که به quick view اشاره نمی‌کرد | تغییر به «مشاهده محصول» با permalink | 861eb04 |

### ۳.۳ عملکرد (Core Web Vitals)

| # | یافته | اصلاح | کامیت |
|---|---|---|---|
| ۱ | بدون اولویت LCP روی نخستین تصویر محصول | fetchpriority="high" شرطی روی نخستین تصویر front-page و wp-post-image در صفحهٔ محصول | 861eb04 |
| ۲ | CSS ووکامرس روی همهٔ صفحات بارگذاری می‌شد | enqueue شرطی روی سطوح تجارت (is_woocommerce، cart، checkout، account، search) | 861eb04 |
| ۳ | jQuery وابستگی همیشگی اسکریپت تم بود | jQuery تنها روی سطوح تجارت؛ هستهٔ main.js بدون jQuery اجرا می‌شود | 861eb04، fccac7b |
| ۴ | حذف emoji و REST در دو تابع مجزا | ادغام در bamero_disable_bloat با مالک واحد | 861eb04 |
| ۵ | دو callback متفاوت برای wp_revisions_to_keep (تعداد ۵ و closure تعداد ۳ برای محصول) | ادغام در bamero_limit_post_revisions (محصول: ۳، سایر: حداکثر ۵) | fccac7b |
| ۶ | اسکریپت‌های سبد-خرید روی همهٔ صفحات | wc-add-to-cart و wc-cart-fragments فقط روی سطوح تجارت | 861eb04 |

### ۳.۴ سئو

| # | یافته | اصلاح | فایل | کامیت |
|---|---|---|---|---|
| ۱ | آرشیو دسته‌بندی بدون H1 واقعی و breadcrumb | H1 با single_term_title()، توضیح ترم، و woocommerce_breadcrumb داخل .breadcrumb-bar | woocommerce/archive-product.php | 4cc92c5 |
| ۲ | قالب override صفحهٔ checkout کپی واژه‌به‌واژه از هستهٔ WooCommerce 9.0 بود (ریسک خاموشی به‌روزرسانی‌های امنیتی) | حذف کامل override؛ هستهٔ WC مالک رندر است | woocommerce/checkout/form-checkout.php (حذف شد) | affb06c |
| ۳ | صفحهٔ 404 بدون مسیر خروج | فهرست لینک‌های سریع (فروشگاه، دربارهٔ ما، تماس با ما، سبد خرید) | 404.php | 4cc92c5 |
| ۴ | robots.txt و meta robots | انتقال بدون تغییر به inc/seo.php؛ robots.txt داینامیک و بی‌وابسته به دامنه | inc/seo.php | fccac7b |

### ۳.۵ معماری CSS

| # | یافته | اصلاح | کامیت |
|---|---|---|---|
| ۱ | دو لایهٔ مدرن موازی (storefront-modern.css و modern-commerce-2026.css) | ادغام کامل در storefront-modern.css و حذف فایل دوم | 3aafdb4، d123f34 |
| ۲ | بلوک‌های :root تکراری | توکن‌ها در variables.css یکتا شدند؛ افزودن --bamero-surface-soft و توکن‌های tint (blue/ink/surface) و --bamero-teal | 3aafdb4 |
| ۳ | نقاط شکست پراکنده | یکسان‌سازی به 420/767/900/1120 | 3aafdb4 |
| ۴ | اهداف لمسی 38 و 42 پیکسل (زیر استاندارد WCAG 2.2) | افزایش add-to-cart به 44px؛ min-block-size عنوان محصول بر مبنای 3.6em | 3aafdb4 |
| ۵ | تضاد static و sticky سربرگ در موبایل | قانون sticky واحد؛ حذف قواعد متعارض | 3aafdb4 |
| ۶ | ۶۹ قانون تک‌خطی چند اعلانی | شکستن به یک اعلان در هر خط (قابل diff و بازبینی) | 3aafdb4 |
| ۷ | پالت theme.json واگرا از توکن‌ها | هم‌سان‌سازی (#0057B8، #F4A300، #152238، #F8F9FA) | 3aafdb4 |
| ۸ | نبود استایل چاپ و قواعد عناصر جدید | افزودن print stylesheet و قواعد .page-hero-description و .error-quick-links | 3aafdb4 |
| ۹ | تعارض floats با sticky CTA در صفحهٔ محصول | اصلاح در woocommerce.css (جابجایی ۷۶ پیکسل + safe-area) | 3aafdb4 |
| ۱۰ | قواعد مردهٔ .filter-chips | حذف | 3aafdb4 |
| ۱۱ | نسخهٔ style.css | ارتقا به 2.2.0 هم‌راستا با BAMERO_VERSION | 3aafdb4 |

### ۳.۶ معماری PHP و JavaScript

| # | تغییر | جزئیات | کامیت |
|---|---|---|---|
| ۱ | functions.php به bootstrap تبدیل شد | ثابت‌ها (BAMERO_VERSION 2.2.0، THEME_DIR، THEME_PATH)، حذف wp_generator و require_once مرتب هفت ماژول | fccac7b |
| ۲ | هفت ماژول inc/ | helpers (توابع خالص)، setup (پشتیبانی‌ها و enqueue ها)، woocommerce (ادغام تجارت)، seo (دادهٔ ساختاریافته)، performance (Core Web Vitals)، security (CSP و هدرها)، forms (فرم‌ها و Customizer) | fccac7b |
| ۳ | js/main.js بازنویسی vanilla-first | کشوی موبایل با classList و aria، گارد کلیدی quantity به‌صورت delegated (مقاوم به AJAX)، ماشین‌حساب پوشش با getAttribute؛ رفتارهای وابسته به ووکامرس (added_to_cart، checkout_error، آکاردئون) در بلوک jQuery-gated | fccac7b |
| ۴ | حفظ کامل رشته‌های فارسی ماشین‌حساب | «مساحت را وارد کنید.»، «پوشش‌دهی محصول ثبت نشده است.»، «مقدار تقریبی: … لیتر با ضریب اطمینان ۱۰٪» | fccac7b |

## ۴. کامیت‌های شاخه

| کامیت | عنوان |
|---|---|
| 8b85d37 | test: binary asset round-trip probe (to be verified, reverted if corrupted) |
| 4a3c1bb | chore(assets): remove corrupted binary probe file (binary upload unsupported via API tooling) |
| bd76231 | chore(assets): remove legacy favicon.png (misnamed JPEG, 161KB) |
| eddac35 | chore(assets): remove legacy logo.png (byte-identical duplicate of favicon, misnamed JPEG) |
| 861eb04 | fix(P1): security, Persian digits, LCP priority, asset integrity |
| 4cc92c5 | fix(SEO): taxonomy-aware H1, real breadcrumb hierarchy, 404 quick links |
| affb06c | refactor(woocommerce): drop checkout template override (verbatim WC 9.0 core copy) |
| 3aafdb4 | refactor(CSS): token unification, breakpoint consolidation, a11y targets, print styles |
| d123f34 | refactor(CSS): remove modern-commerce-2026.css (merged into storefront-modern.css) |
| fccac7b | refactor(theme): split functions.php into inc/ modules + vanilla-first main.js |

## ۵. موارد به تعویق افتاده (با دلیل مستند)

### ۵.۱ آیکون raster برای apple-touch-icon

iOS Safari برای صفحهٔ خانهٔ iOS به PNG نیاز دارد. API متن‌محور GitHub قادر به آپلود باینری سالم نیست؛ آزمون round-trip اثبات کرد فایل ذخیره‌شده خراب می‌شود (کامیت‌های 8b85d37 و 4a3c1bb). تا زمان انجام گام دستی زیر، apple-touch-icon به logo.svg اشاره می‌کند که در iOS به افتادن آیکون می‌انجامد اما خطا یا اختلالی ایجاد نمی‌کند.

گام دستی پس از استقرار (دقیقه‌ای): تولید apple-touch-icon.png با ابعاد ۱۸۰×۱۸۰ از logo.svg، بارگذاری در پوشهٔ images تم، و افزودن یک خط link به header.php کنار favicon.svg.

### ۵.۲ نگاشت gettext در برابر فایل‌های .po

ترجمه‌های فارسی ووکامرس از طریق آرایهٔ runtime در gettext اعمال می‌شوند نه فایل‌های .po. این رویکرد برای تک‌فروشگاه ایرانی سریع‌تر، بدون وابستگی به ابزار ترجمه و بی‌نیاز از کامپایل است. مهاجرت به .po تنها در صورت نیاز به همکاری مترجم‌های خارجی منطقی است.

## ۶. گیت‌های runtime پس از مرج

مطابق دامنهٔ اثبات [GO_LIVE_VERIFICATION_REPORT.md](GO_LIVE_VERIFICATION_REPORT.md)، موارد زیر صرفاً روی محیط واقعی قابل تأییدند و در روز راه‌اندازی طبق [GO_LIVE_RUNBOOK_FA.md](GO_LIVE_RUNBOOK_FA.md) بخش ۹ اجرا می‌شوند: رندر مرورگر/موبایل، Core Web Vitals واقعی، پرداخت واقعی زرین‌پال، تحویل SMS و خودِ فرایند استقرار.

## ۷. جمع‌بندی

تم بامرو نسخهٔ ۲.۲.۰ با این شاخه از نظر امنیت، محلی‌سازی، عملکرد، سئو و معماری در سطح کد کامل، استاندارد و آمادهٔ Go-Live است. همهٔ یافته‌های ممیزی رفع و در این سند ردیف‌به‌ردیف اثبات شده‌اند. تنها دو مورد به تعویق افتاده وجود دارد که هر دو در بخش ۵ با دلیل و راه‌حل مستند شده‌اند.
