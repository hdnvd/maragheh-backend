# مراغه — Maragheh City App

اخبار، تصاویر، جاذبه‌های گردشگری، مشاهیر و آب و هوای مراغه

| Part | Folder | Tech | Hosted at |
|---|---|---|---|
| Android app | `android/` | Java, Material 3, Retrofit, Glide | Cafebazaar (`com.sweetsoft`) |
| API + crawler | `backend/` | Laravel 12, MySQL, Sanctum | `https://api.maraghehcity.ir/v1` |
| Admin panel | `admin/` | Angular 22, Angular Material (RTL) | `https://admin.maraghehcity.ir` |

---

## 1. Backend (Laravel) on cPanel — no Terminal needed

Composer can't run on the server, so GitHub builds a ready-to-upload zip (with `vendor/`), and the
one-off setup commands run through a **temporary cron job**.

> `backend/.env`, `backend/database/database.sqlite` and any `backend.zip` you made yourself on this PC are a
> **local test setup** (debug mode, SQLite, test admin). **Never upload them** — always use the zip from GitHub Actions.

### 1.1 One-time cPanel setup
1. **MySQL® Databases**: create a database and a user, add the user to the database with **ALL PRIVILEGES**.
   Note the full names (cPanel prefixes them, e.g. `cpuser_maragheh`).
2. **MultiPHP Manager**: tick `api.maraghehcity.ir` → select **PHP 8.3** → Apply (the server default 8.1 is too old for
   Laravel 12; 8.2 or 8.4 also work — then use `ea-php82` / `ea-php84` instead of `ea-php83` in the cron commands below).
   **Select PHP Version → Extensions**: `pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, gd, curl, dom`
   (+ `exif`, optional, fixes photo rotation).
3. **Domains** → `api.maraghehcity.ir` → **Document Root** = `/home/USER/maragheh-api/public`
   (USER = your cPanel username). If your host doesn't let you change it, use the folder it gives you instead of
   `maragheh-api` — the included root `.htaccess` routes everything into `public/` and blocks `.env`.
4. **SSL/TLS Status**: run AutoSSL for `api.maraghehcity.ir` and `admin.maraghehcity.ir`.

### 1.2 Get the deploy package
GitHub → **hdnvd/maragheh-backend → Actions → "Deploy package"** → newest green run → **Artifacts** →
download `maragheh-backend-N` (it is built automatically on every push; unzip it once on your PC to get
`maragheh-backend.zip`).

### 1.3 Upload
1. **File Manager** → create `/home/USER/maragheh-api` → **Upload** `maragheh-backend.zip` → right-click → **Extract**
   → delete the zip.
2. In that folder, copy `.env.example` to `.env` (enable *Settings → Show Hidden Files*) and **Edit** it:
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — from step 1.1
   - `KAVENEGAR_API_KEY`, `KAVENEGAR_TEMPLATE` — see 1.8
   - leave `APP_KEY=` empty (it is generated in the next step) and keep `APP_ENV=production`, `APP_DEBUG=false`.

### 1.4 Run the setup once (temporary cron job)
**Cron Jobs → Add New Cron Job**, *Common Settings: Once Per Minute*, command:
```
cd /home/USER/maragheh-api && /opt/cpanel/ea-php83/root/usr/bin/php artisan app:deploy --admin-email=you@example.com --admin-password=ChooseAStrongPass >> storage/logs/deploy.log 2>&1
```
Wait 1–2 minutes, open `storage/logs/deploy.log` in File Manager — it must end with
**`Deploy finished.`** — then **delete this cron job** (it would keep resetting the admin password).
`app:deploy` generates `APP_KEY`, runs migrations, adds the Mehr News source, creates the admin,
prepares `public/storage` for uploads (no symlink needed) and caches config/routes. Change the admin
password afterwards in the panel (**تغییر رمز عبور**).

> Cron jobs must name the PHP version explicitly: the plain `/usr/local/bin/php` uses the server default (8.1) and
> fails with *"Composer detected issues in your platform … PHP >= 8.2"*. If `deploy.log` says the path is
> *not found*, try `/usr/local/bin/ea-php83`.

Check: `https://api.maraghehcity.ir/v1/sources` should return `{"data":[{"id":1,"name":"خبرگزاری مهر"}]}`.

> **If `deploy.log` shows a migration `FAIL`**, the database is left half-created and the next run fails with
> *"Table … already exists"*. Fix the cause, then in **phpMyAdmin** select the database → tick **all** tables →
> *Drop*, and run the cron job again. (Tables are created as InnoDB with 191-char indexed strings, so hosts whose
> MySQL defaults to MyISAM — error *1071 Specified key was too long; max key length is 1000 bytes* — work.)

### 1.5 Permanent cron job (news crawler)
**Cron Jobs** → *Once Per Minute*:
```
cd /home/USER/maragheh-api && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```
It fetches Mehr News every 30 minutes (plus daily cleanup). You can also fetch immediately from the admin
panel → **منابع خبری → دریافت اکنون**.

### 1.6 Updating later
Download the newest artifact, upload and extract it over `/home/USER/maragheh-api` (overwrite). Your
`.env` and the uploaded photos in `public/storage/` are not in the zip, so they are kept.
Extracting replaces `public/.htaccess`, where cPanel may store the domain's PHP version — open **MultiPHP Manager**
afterwards and check `api.maraghehcity.ir` still shows PHP 8.3 (re-apply it if not). Then run the
1.4 cron job again **without** the `--admin-…` options:
```
cd /home/USER/maragheh-api && /opt/cpanel/ea-php83/root/usr/bin/php artisan app:deploy >> storage/logs/deploy.log 2>&1
```
and delete it after `Deploy finished.` appears.

> Configuration is cached, so after **any edit to `.env`** run that same `app:deploy` cron job once more.

### 1.7 Crawler notes
- Source: `https://www.mehrnews.com/tag/مراغه` — the list page plus each new article (title, lead, body, photo, exact publish time).
- More sources (another Mehr tag or any RSS feed with an optional keyword filter) can be added in the admin panel → **منابع خبری**.
- `NEWS_STORE_FULL_BODY=false` stores only title/summary/photo and the app links to the original article. **Check Mehr News' republishing terms**; the app always shows the source name and a «مشاهده در منبع خبر» link.
- Hiding/“deleting” a news item in the panel keeps it in the DB as hidden so the crawler won't import it again.

### 1.8 Kavenegar OTP
panel.kavenegar.com → **ارسال پیامک با الگو (Verify Lookup)** → create a template, e.g. name `maragheh-otp`:
```
کد ورود شما به اپلیکیشن مراغه: %token
```
After it is approved, put the template name in `KAVENEGAR_TEMPLATE` and your API key in `KAVENEGAR_API_KEY`.
With `APP_ENV=local` and no key, codes are written to `storage/logs` instead of being sent (development only).

### 1.9 API overview (`/v1`)
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
npm run build            # uses src/environments/environment.prod.ts → https://api.maraghehcity.ir/v1
```
Upload **the contents of** `admin/dist/admin/browser/` to the document root of `admin.maraghehcity.ir`.
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
