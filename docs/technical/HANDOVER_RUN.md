# Handover run and check

**For the next agent or person.** This is the order to bring FurEscue up and confirm the web server and the Android app talk to the same database. Install details, PHP extensions, and the MySQL user SQL stay in [HOW_TO_RUN.md](HOW_TO_RUN.md). Product behavior stays in [WORKFLOWS.md](WORKFLOWS.md) and `src/Http/Routes`. Phone rules stay in `D:\pepot\furescue-app\docs\02-how-to-run.md`. The same run checklist, written for an agent that opened the Flutter repo, is `D:\pepot\furescue-app\docs\04-handover-run.md`.

Open both repos. Leave `D:\pepot\marinesee` alone.

| Repo | Path | Role |
|------|------|------|
| Web + API | `D:\pepot\furescue` | The only server and the only database |
| Android | `D:\pepot\furescue-app` | Flutter client. Resident and rescuer only |

One PHP process serves pages and `/api/v1`. There is no second API.

## Which database

| Job | Database | Env file | How it is filled |
|-----|----------|----------|------------------|
| Click through web and phones | `furescue` | `.env` | `php seeders\seed.php` |
| PHPUnit MySQL, live HTTP, Playwright, phone `integration_test` | `furescue_test` | `.env.test` | `php bin\reset-test-db.php` |

A hand check uses `furescue`. Automated suites use `furescue_test`. Do not point `php -S` at the demo database and a test suite at it in the same pass. `bin\serve-test.bat` loads `.env.test` and does not overwrite `.env`.

`src/Database.php` falls back to Postgres when `DB_DRIVER` is missing. `.env` must contain `DB_DRIVER=mysql` before migrate, seed, or serve.

## 1. First time on this machine

Follow [HOW_TO_RUN.md](HOW_TO_RUN.md) through the MySQL user, `.env`, `composer install`, and `npm run build`. Then return here.

Minimum `.env` for a local check:

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=furescue
DB_USER=furescue
DB_PASS=furescue_pass
APP_SECRET=change_me_app_secret
JWT_SECRET=change_me_jwt_secret
JWT_REFRESH_SECRET=change_me_refresh_secret
CORS_ALLOWED_ORIGINS=http://127.0.0.1:8000
```

Create the empty schema once (SQL is in HOW_TO_RUN Step 6) if it does not exist.

Flutter SDK on PATH, Android SDK, and a physical phone (Android 8 / API 26 or higher) are required before section 5. Do not start an emulator.

## 2. Database: migrate, then seed

From `D:\pepot\furescue`:

```bat
php bin\migrate.php
php seeders\seed.php
```

`migrate.php` applies new files in `migrations/` and records them in `migrations_log`. It does not insert demo rows.

`seed.php` is the full demo and it is safe to run again. It fills permissions, accounts, e-learning, reports, cases, animals, health, adoptions, messages, notifications, and the extra juan / rescuer rows in `seed_demo_roles.php`.

| Command | Use it when |
|---------|-------------|
| `php seeders\seed.php` | Web and phone screens should already have reports, cases, animals, messages, and lessons |
| `php seeders\seed_users.php` | You only need to log in. Lists stay empty |
| `php bin\migrate.php --fresh --yes` | You want an empty schema. This drops every table. Run `php seeders\seed.php` again afterward or login fails |

A domain seeder (`seed_reports.php`, `seed_cases.php`, and the others listed at the top of `seeders\seed.php`) can be run alone after accounts exist. For a handover check, run `seed.php` once and stop.

## 3. Accounts

Every seeded account uses **`Password123!`**.

| Role | Email | Where to check |
|------|-------|----------------|
| Resident | `juan@furescue.local` | Web `/reports/`. Phone resident shell. Also `maria@`, `ana@`, `pedro@`, `rosa@`, `miguel@` |
| Rescuer, on duty | `rescuer@furescue.local` through `rescuer5@furescue.local` | Web `/cases/`. Phone rescuer shell |
| Rescuer, pending | `rescuer6@furescue.local`, `rescuer7@furescue.local` | Blocked until an admin approves them on web |
| Admin | `admin@furescue.local` | Web `/admin/` only. The phone must show “use the web console” and stop |

Give each phone a different resident or rescuer. Two phones on `juan@` will share one inbox.

## 4. Start the server

Browser-only, from `D:\pepot\furescue`:

```bat
php -S 127.0.0.1:8000 -t public public\index.php
```

Phones on the same Wi-Fi need every interface, not loopback:

```bat
php -S 0.0.0.0:8000 -t public public\index.php
```

Leave that window open. Confirm login from the laptop:

```bat
curl -X POST http://127.0.0.1:8000/api/v1/auth/login -H "Content-Type: application/json" -d "{\"email\":\"juan@furescue.local\",\"password\":\"Password123!\"}"
```

`success: true` and a `tokens.access_token` means the API and the seed are up. A database error here is migrate, `.env`, or MySQL, not the Flutter app.

Web pages to open while that server is running:

| Page | URL |
|------|-----|
| Landing | http://127.0.0.1:8000/ |
| Login | http://127.0.0.1:8000/auth/login.php |
| Resident home | http://127.0.0.1:8000/reports/ |
| Rescuer home | http://127.0.0.1:8000/cases/ |
| Admin | http://127.0.0.1:8000/admin/ |

If the pages have no styling, run `npm run build` from the web repo root and reload.

## 5. Point a phone at that server

`http://127.0.0.1:8000` on the phone is the phone. The app default (`app/lib/api/config.dart`) expects USB port reverse.

**One phone, USB debugging on**

```bat
adb reverse tcp:8000 tcp:8000
cd D:\pepot\furescue-app\app
flutter pub get
flutter devices
flutter run
```

`flutter devices` must list the phone. If it only lists Windows or Chrome, fix the cable or the driver. Do not launch an emulator.

**Several phones, no cable**

1. Server command is `php -S 0.0.0.0:8000` (section 4).
2. On the laptop, `ipconfig`. Use the Wi-Fi IPv4 address, for example `192.168.1.20`.
3. Allow inbound TCP 8000 on private networks when Windows Firewall asks.
4. Each phone joins that same Wi-Fi. In the phone browser, open `http://192.168.1.20:8000` before installing anything.
5. Build the APK with that address baked in:

```bat
cd D:\pepot\furescue-app\app
flutter build apk --release --dart-define=API_BASE_URL=http://192.168.1.20:8000
```

APK: `app\build\app\outputs\flutter-apk\app-release.apk`. Send that file to each phone and install it. If the laptop IP changes, build again. The old APK still points at the old address.

`flutter run` with hot reload is one device at a time. For several people tapping at once, use the release APK.

## 6. What a pass looks like

Do these in order. Stop at the first failure and fix that layer before blaming the next one.

1. `php bin\migrate.php` finishes without a connection error.
2. `php seeders\seed.php` finishes. Re-running it does not duplicate the demo accounts into a broken login.
3. The juan login `curl` returns `success: true`.
4. Browser: juan reaches `/reports/`, rescuer reaches `/cases/`, admin reaches `/admin/`.
5. Phone: juan opens the resident shell and sees seeded reports. `rescuer@furescue.local` opens the rescuer shell and sees seeded cases. `admin@furescue.local` is stopped with the web-console message.
6. A second phone, different account, reaches the same server.

These stay manual. Do not treat a missing result here as a seed failure: phone GPS submit, a real camera photo, Google sign-in, and 3D playback on an animal that has `model_3d_url`. The scripted path for report → verify → assign → accept → proof → admin resolve is Playwright (`npx playwright test e2e/ipo.spec.ts`) against `furescue_test`, and it mocks GPS.

Case **resolve** is admin-only on the API. The phone uploads proof. City Vet resolves on the web. The app does not push alerts while it is closed. Both are current behavior, recorded in `D:\pepot\furescue-app\docs\03-handover.md`.

## 7. Automated check (separate from the demo)

Only after `.env.test` exists and `DB_NAME` is `furescue_test`. Setup is [TESTING.md](TESTING.md).

```bat
cd D:\pepot\furescue
php vendor\phpunit\phpunit\phpunit
php bin\reset-test-db.php
php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml.dist
```

Phone widget tests do not need a server:

```bat
cd D:\pepot\furescue-app\app
flutter test
```

Live HTTP and Playwright need `bin\serve-test.bat` left open, then the commands in TESTING.md. That server is the test database. The demo logins in section 3 exist there too, because `reset-test-db.php` runs `seed.php` against `furescue_test`.
