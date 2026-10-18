# مراغه — Maragheh City App

اخبار، تصاویر، جاذبه‌های گردشگری، مشاهیر و آب و هوای مراغه

| Part | Folder | Tech | Hosted at |
|---|---|---|---|
| Android app | `android/` | Java, Material 3, Retrofit, Glide | Cafebazaar (`com.sweetsoft`) |
| API + crawler | `backend/` | Laravel 12, MySQL, Sanctum | `https://api.maraghecity.ir/v1` |
| Admin panel | `admin/` | Angular 22, Angular Material (RTL) | `https://admin.maraghecity.ir` |

---

## 1. Backend (Laravel) on cPanel

### 1.1 Create the database
cPanel → **MySQL® Databases**: create a database and a user, add the user to the database with **ALL PRIVILEGES**.

> `backend/.env` and `backend/database/database.sqlite` in this folder are a **local test setup** (SQLite, test admin
> `admin@maraghecity.ir` / `TestPass123`). Do not upload them; create the server `.env` from `.env.example` as below.
> `composer.json` pins `config.platform.php = 8.2.0`, so `composer install` resolves packages that run on PHP 8.2–8.4.

### 1.2 Upload the code
1. Upload the `backend/` folder **outside** `public_html`, e.g. `/home/USER/maragheh-api`.
   - If you build locally, run `composer install --no-dev --optimize-autoloader` first and upload with `vendor/`.
   - Or, if cPanel has **Terminal**, upload without `vendor/` and run the same command there.
2. cPanel → **Domains** → `api.maraghecity.ir` → set the **Document Root** to `/home/USER/maragheh-api/public`.
3. cPanel → **MultiPHP Manager**: PHP **8.2+** for the subdomain. Required extensions (Select PHP Version → Extensions):
   `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, gd, curl, dom, exif` (exif optional, fixes photo rotation).
4. Enable **SSL** (AutoSSL / Let's Encrypt) for `api.maraghecity.ir` and `admin.maraghecity.ir`.

### 1.3 Configure
```bash
cd ~/maragheh-api
cp .env.example .env
php artisan key:generate
```
Edit `.env`: `DB_*`, `KAVENEGAR_API_KEY`, `KAVENEGAR_TEMPLATE` (see 1.6).

```bash
php artisan migrate --force
php artisan db:seed --force          # adds the Mehr News «مراغه» tag source
php artisan storage:link             # public URLs for uploaded photos
php artisan admin:create you@example.com --name="مدیر"   # asks for a password
php artisan config:cache && php artisan route:cache
php artisan news:crawl               # first import; prints "خبرگزاری مهر: N new"
```

> If `storage:link` fails because `symlink()` is disabled on your host, create the link with the cPanel File Manager or ask the host to run it:
> `ln -s /home/USER/maragheh-api/storage/app/public /home/USER/maragheh-api/public/storage`

### 1.4 Cron job (news crawler)
cPanel → **Cron Jobs** → *Once Per Minute* (`* * * * *`):
```
cd /home/USER/maragheh-api && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```
The scheduler then runs `news:crawl` every 30 minutes (plus daily cleanup). Check the PHP path with `which php` in Terminal; on some hosts it is `/opt/cpanel/ea-php82/root/usr/bin/php`.

### 1.5 Crawler notes
- Source: `https://www.mehrnews.com/tag/مراغه` — the list page plus each new article (title, lead, body, photo, exact publish time).
- More sources (another Mehr tag or any RSS feed with an optional keyword filter) can be added in the admin panel → **منابع خبری**.
- `NEWS_STORE_FULL_BODY=false` stores only title/summary/photo and the app links to the original article. **Check Mehr News' republishing terms**; the app always shows the source name and a «مشاهده در منبع خبر» link.
- Hiding/“deleting” a news item in the panel keeps it in the DB as hidden so the crawler won't import it again.

### 1.6 Kavenegar OTP
panel.kavenegar.com → **ارسال پیامک با الگو (Verify Lookup)** → create a template, e.g. name `maragheh-otp`:
```
کد ورود شما به اپلیکیشن مراغه: %token
```
After it is approved, put the template name in `KAVENEGAR_TEMPLATE` and your API key in `KAVENEGAR_API_KEY`.
With `APP_ENV=local` and no key, codes are written to `storage/logs` instead of being sent (development only).

### 1.7 API overview (`/v1`)
| Method | Path | Notes |
|---|---|---|
| GET | `home` | weather + 5 news + attractions + celebrities + photos |
| GET | `news?q=&from=YYYY-MM-DD&to=&source_id=&page=` | dates are Tehran calendar days |
| GET | `news/{id}`, `sources`, `weather` | |
| GET | `posts?type=attraction\|celebrity&q=`, `posts/{id}` | |
| GET | `photos` | approved gallery |
| POST | `auth/otp/request` `{phone}` → `auth/otp/verify` `{phone, code, name?}` | returns a Sanctum token |
| GET/POST | `me/photos` (Bearer) | user's submissions / upload (multipart `image,title,description`) |
| * | `admin/...` | admin panel (Bearer admin token) |

Limits: OTP request 5 per 10 min per IP and 1 per 2 min per phone; 10 photo uploads per user per day, max 3 pending at once.

---

## 2. Admin panel (Angular)

```bash
cd admin
npm ci
npm run build            # uses src/environments/environment.prod.ts → https://api.maraghecity.ir/v1
```
Upload **the contents of** `admin/dist/admin/browser/` to the document root of `admin.maraghecity.ir`.
The included `.htaccess` handles HTTPS redirect and Angular routes.

Local development: `php artisan serve` in `backend/` (with `APP_ENV=local`) and `npm start` in `admin/` → http://localhost:4200.

Panel sections: داشبورد · اخبار (show/hide, preview) · منابع خبری (add, edit, «دریافت اکنون») · جاذبه‌های گردشگری / مشاهیر (rich text, cover, gallery, map location, ordering, publish toggle) · تصاویر (approve / reject with reason / upload / block sender) · کاربران اپلیکیشن · تغییر رمز.

---

## 3. Android app

- Open `android/` in Android Studio (JDK 17+), or build from the command line.
- `applicationId com.sweetsoft`, **versionCode 38 / versionName 4.0** (live on Cafebazaar: 37 / 3.05).
- minSdk 23, targetSdk 36. RTL, Vazirmatn font, light/dark theme.

### Signing for Cafebazaar
The update **must** be signed with the same key as version 3.05.
```bash
cd android
copy keystore.properties.example keystore.properties   # then fill in path, passwords, alias
gradlew assembleRelease
```
Output: `android/app/build/outputs/apk/release/app-release.apk` → upload in the Cafebazaar developer panel.
`keystore.properties` and `*.jks` are git-ignored; keep them backed up.

### Test against a local backend
```bash
gradlew assembleDebug -PapiBaseUrl=http://10.0.2.2:8000/v1/
```
(debug builds only allow plain HTTP to 10.0.2.2 / localhost)

### Before publishing
- «درباره ما» text lives in `android/app/src/main/res/values/strings.xml` (`about_text`).
- Store listing assets: `assets/cafebazaar_icon_512.png`.
