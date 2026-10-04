# FurEscue testing pyramid

Daily work stays off MySQL and off the phone. Live layers share one dedicated schema: **`furescue_test`**. Never point tests at the demo `furescue` database.

```
flutter test / PHPUnit SQLite     (no server)
        ↓
PHPUnit @group mysql              (migrations on furescue_test)
        ↓
HTTP smoke + Playwright           (php -S via bin\serve-test.bat)
        ↓
Flutter integration_test          (physical phone + adb reverse)
```

## One-time test database

```bat
copy .env.test.example .env.test
:: set DB_USER / DB_PASS to the same local MySQL user as .env
php bin\create-test-db.php
php bin\reset-test-db.php
```

If `create-test-db.php` says access denied, the app user cannot create schemas. Either set `FURESCUE_TEST_DB_ADMIN_USER` / `FURESCUE_TEST_DB_ADMIN_PASS` (root) in `.env.test`, or run this as a MySQL admin:

```sql
CREATE DATABASE IF NOT EXISTS furescue_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON furescue_test.* TO 'furescue'@'localhost';
FLUSH PRIVILEGES;
```

`reset-test-db.php` loads `.env.test` (it does not overwrite `.env`), runs `migrate --fresh --yes`, then `seeders\seed.php`. It exits if `DB_NAME` is not `furescue_test`.

## Commands

```bat
:: daily (no server)
cd d:\pepot\furescue
php vendor\phpunit\phpunit\phpunit
cd d:\pepot\furescue-app\app
flutter test

:: MySQL API (after create-test-db + reset-test-db)
cd d:\pepot\furescue
php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml.dist

:: live HTTP + Playwright (keep this terminal open)
cd d:\pepot\furescue
bin\serve-test.bat
:: other terminal:
php vendor\phpunit\phpunit\phpunit -c phpunit.live.xml.dist
npx playwright test

:: phone (USB debugging on)
adb reverse tcp:8000 tcp:8000
cd d:\pepot\furescue-app\app
flutter test integration_test -d <phone-id>
```

`composer test` is the SQLite suite only. `@group mysql` and `@group live` are excluded from `phpunit.xml.dist`.

Playwright needs `npm install` and `npx playwright install chromium` once, plus `npm run build` so CSS exists. `baseURL` is `http://127.0.0.1:8000`.

`npx playwright test e2e/ipo.spec.ts` walks report → verify → assign → accept → proof → admin resolve with **mocked geolocation** and **fixture files**. It does not open a camera. Phone GPS and a real camera stay manual.

Phone tests inject `MemorySessionStore` so the Android keystore does not flake. Default `API_BASE_URL` is `http://127.0.0.1:8000` (correct with `adb reverse`).

## Dual auth

| Client | Auth | After login |
|--------|------|-------------|
| Web form `POST /auth/login.php` | PHP `$_SESSION` + page-issued JWT for JS islands | Role home (`/reports/`, `/cases/`, `/admin/`) |
| Web signup / API | JWT in `localStorage` | Same homes |
| Flutter | Bearer JWT on device | ResidentShell / RescuerShell / AdminBlockedScreen |

Seed password is `Password123!`. Accounts: `juan@`, `rescuer@`, `admin@`, pending `rescuer6@` — see [HOW_TO_RUN.md](HOW_TO_RUN.md) §3.

## Manual only (do not fake-automate)

- Phone GPS report submit (the browser IPO spec mocks geolocation instead)
- Real camera, device proof photos, and profile photo
- Real Google OAuth
- 3D / 360 playback on an animal that has `model_3d_url` or a 360 set

Checklist: sign in as each role, file one in-Mati report from a phone, verify it on web, accept with real camera proof, open an animal detail that has a 3D control if a URL exists, and try Google sign-in on `/auth/login.php`. Admin resolve stays on `/admin/cases/case-detail.php`.
