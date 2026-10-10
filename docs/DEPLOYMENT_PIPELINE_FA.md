# Bamero — Deployment Pipeline (استقرار یک‌بار-ساخت، ترویج همان artifact)

این سند pipeline استقرار را مطابق اصول Google SRE (release/rollback based)، NIST SSDF و DORA توصیف می‌کند. **معماری پروژه تغییر نمی‌کند**؛ GitHub Actions فقط orchestration است و `scripts/build-release.sh` تنها builder canonical باقی می‌ماند.

## جریان

```
PR → CI (static-production-gate)
   → Build یک‌بار (build-once) → artifact + SHA256
   → Staging deploy (همان artifact)
   → Staging verification (HTTP/health)
   → Production approval (environment protection)
   → Production deploy (همان artifact + DB backup الزامی)
   → Post-deploy verification
   → در صورت شکست: rollback به release قبلی (بدون rebuild)
```

- **یک artifact، چند environment**: هر environment دوباره build نمی‌کند؛ همان zip ساخته‌شده در `build-once` با checksum تأیید و promote می‌شود.
- **شکست هر gate = توقف pipeline**: هیچ `continue-on-error` وجود ندارد.
- **Production فقط با approval**: environment `production` باید در GitHub UI با «required reviewers» محافظت شود؛ secretهای production فقط در همان environment تعریف می‌شوند و قبل از approval در اختیار هیچ jobی نیست.
- **Concurrency**: برای هر environment فقط یک deployment هم‌زمان مجاز است.

## آنچه مالک مخزن باید در GitHub UI تنظیم کند (از طریق API قابل انجام نیست)

1. ساخت environment با نام دقیق `staging` و `production`.
2. برای `production`: افزودن required reviewers.
3. Secretهای هر environment (نام‌ها دقیقاً):
   - `DEPLOY_SSH_KEY` — کلید خصوصی SSH (کلید اختصاصی deployment توصیه می‌شود)
   - `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PATH` — مقصد SSH و مسیر WordPress
   - `STAGING_URL` / `PRODUCTION_URL` — برای post-deploy verification
4. Branch protection روی `main`: الزام check سبز `static-production-gate` + review.

## قرارداد host مقصد (fail-closed)

- احراز هویت SSH با key برای `DEPLOY_USER`
- دستور `wp` (wp-cli) در دسترس در `DEPLOY_PATH`
- ساختار: `releases/<tag>/` + symlink `current`
- اگر هر یک از متغیرهای موردنیاز present نباشد، `deploy-release.sh` **ABORT** می‌کند؛ deployment جعلی انجام نمی‌شود.

## Rollback

- `scripts/rollback-release.sh` فقط symlink `current` را به release قبلی برمی‌گرداند؛ **بدون rebuild و بدون تغییر دیتابیس**.
- **محدودیت صریح**: اگر در release جدید migration واقعی دیتابیس اجرا شده باشد، rollback کد، دادهٔ migrate شده را برنمی‌گرداند. seedهای Bamero idempotent هستند، اما rollback بعد از ثبت سفارش واقعی باید بازبینی دستی داشته باشد.
- پس از rollback، health check خودکار اجرا می‌شود.

## NOT VERIFIED (به‌صورت طراحی)

موجودیت configuration هرگز proof نیست. تا زمان اجرا در محیط واقعی، این موارد **NOT VERIFIED** می‌مانند:

- ZarinPal payment E2E (تراکنش واقعی)
- SMS.ir delivery E2E (ارسال واقعی OTP)
- رندر مرورگر/موبایل صفحات اصلی
- WP-Cron و قابل‌نوشتن‌بودن uploads در host واقعی
- restore از DB backup

## اصلاح B6 (۲۰۲۶-۱۰): مسیر HOME سرور مقصد

`deploy-release.sh` مسیر HOME سرور را یک‌بار پیش از upload به‌صورت literal استخراج می‌کند (`REMOTE_HOME`). رشتهٔ `\$HOME` قبلی برای دو دلیل شکست می‌خورد: scp در OpenSSH با نسخهٔ ۹ و بالاتر به‌صورت پیش‌فرض از پروتکل SFTP استفاده می‌کند که هیچ expand مسیری انجام نمی‌دهد؛ و مسیر پشتیبان DB در production داخل single-quote بود (بدون expand). مسیر literal برای ssh، scp (هر دو پروتکل) و heredoc یکسان کار می‌کند.

## تفکیک نقش‌ها

- `scripts/quick-install.sh` = **bootstrap** محیط local/staging از source. مسیر deployment نیست.
- `scripts/deploy-release.sh` = **deployment** از artifact ساخته‌شده. هرگز برای deployment به دانلود لحظه‌ای plugin یا خاموش‌کردن `DISALLOW_FILE_MODS` متوسل نمی‌شود.

## لایه‌های تقویتی L2 (۲۰۲۶-۱۰)

۱) گیت static: `bash -n` روی تمام اسکریپت‌های shell، `shellcheck --severity=error` روی چهار اسکریپت پایپ‌لاین استقرار، و اعتبارسنجی YAML گردش‌کارها.
۲) `deploy-release.sh`: گارد حجم پشتیبان DB (رد dump خالی/گم‌شده پیش از تعویض symlink) و `wp cache flush` پس از تعویض.
۳) `verify-deployment.sh`: retry سه‌مرحله‌ای برای خطاهای گذر شبکه/5xx (حذف قرمز کاذب) و دو endpoint جدید (cart و my-account).
۴) `health-monitor.yml` (جدید): راستی‌آزمایی شبانهٔ runtime با issue خودکارِ بدون تکرار؛ URLها به‌عنوان Repository Variables ثبت می‌شوند.
