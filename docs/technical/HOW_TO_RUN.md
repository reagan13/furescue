# FurEscue — How to Run the System

Step-by-step guide to install, configure, and run the **FurEscue** rescue-management system on a fresh Windows machine.

FurEscue runs on **one server**: a single PHP built-in server serves both the API (`/api/v1/...`) and all static pages/uploads from the single web root `public/`. Every command below is run from the **repository root** (the folder containing `composer.json`).

---

## 1. What you need to install (with download links)

| # | Software | Version | Why | Download |
|---|----------|---------|-----|----------|
| 1 | **PHP** | 8.1+ (recommended 8.2/8.3) | Runs the app (API + built-in web server) | https://windows.php.net/download/ → "VS16 x64 Non Thread Safe" ZIP |
| 2 | **MySQL** | 8.0.13+ (any 8.x) | Database (schema uses `UUID()` defaults) | https://dev.mysql.com/downloads/installer/ → MySQL Community Server |
| 3 | **Composer** | Latest (2.x) | Installs PHP dependencies | https://getcomposer.org/download/ |
| 4 | **Node.js + npm** | 18+ (LTS) | Compiles the Tailwind CSS | https://nodejs.org/ |
| 5 | **Git** *(optional)* | Latest | Clone the repository | https://git-scm.com/ |

---

## 2. Step-by-step setup

### Step 1 — Install PHP and add to PATH
1. Download the **Non Thread Safe** ZIP from https://windows.php.net/download/.
2. Extract to a folder, e.g. `C:\php`.
3. In the folder, copy `php.ini-development` → `php.ini`.
4. Edit `C:\php\php.ini` and uncomment these lines (remove the leading `;`):
   ```ini
   extension=mysqli
   extension=pdo_mysql
   extension=pdo_sqlite
   extension=openssl
   extension=mbstring
   extension=curl
   ```
   Also set:
   ```ini
   extension_dir = "ext"
   ```
5. Add `C:\php` to the system **PATH**:
   - `Win + R` → `sysdm.cpl` → **Advanced** → **Environment Variables** → under **Path** → **Edit** → **New** → `C:\php` → OK.
6. Open a **new** terminal and verify:
   ```bat
   php -v
   ```

### Step 2 — Install MySQL
1. Run the MySQL Installer and choose **MySQL Server** (8.x) + **MySQL Workbench** (optional GUI).
2. Set a **root password** you will remember.
3. During/after install, note the port (default **3306**).

### Step 3 — Install Composer
1. Run the Composer setup installer.
2. After install, verify in a new terminal:
   ```bat
   composer --version
   ```

### Step 4 — Install Node.js
1. Run the Node.js installer (defaults are fine).
2. Verify in a new terminal:
   ```bat
   node -v
   npm -v
   ```

### Step 5 — Get the project
```bat
git clone <your-repo-url> Furescue
cd Furescue
```
*(If you already have the project folder, just open it. All remaining steps run from this folder — the repo root.)*

### Step 6 — Create the database and user
Open **MySQL** (Command Line Client or Workbench) and run:

```sql
CREATE DATABASE furescue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'furescue'@'localhost' IDENTIFIED BY 'furescue_pass';
GRANT ALL PRIVILEGES ON furescue.* TO 'furescue'@'localhost';
FLUSH PRIVILEGES;
```

### Step 7 — Configure the environment
1. Copy the example config at the repo root:
   ```bat
   copy .env.example .env
   ```
2. Edit `.env` (repo root) and set the database credentials:
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

   # Comma-separated origins allowed to call the API cross-origin.
   # Leave empty to fall back to Access-Control-Allow-Origin: * (a warning is logged).
   CORS_ALLOWED_ORIGINS=http://127.0.0.1:8000

   # Required key to open the dev DB viewer at /dbtool/?key=...
   DEVTOOL_KEY=change_me_devtool_key
   ```
   *The Mati City geovalidation bounds and Google client ID are pre-filled and fine to keep.*

### Step 8 — Install PHP dependencies
From the repo root:
```bat
composer install
```
*(Requires internet. Installs `vlucas/phpdotenv` + `firebase/php-jwt` + PHPUnit into `vendor/`.)*

### Step 9 — Run database migrations
```bat
php bin\migrate.php
```
You should see each SQL file reported as `apply ...`. This creates all tables.

### Step 10 — Seed data (optional)

Auth-only (users, roles, permissions — no reports or medical records):
```bat
php seeders\seed_users.php
```

Full demo dataset (about 100+ rows per domain — reports, cases, animals, health, adoptions, messages, notifications, e-learning):
```bat
php seeders\seed.php
```

`seed.php` is an orchestrator. Domain files can also be run on their own after accounts exist: `seed_accounts.php`, `seed_reports.php`, `seed_cases.php`, `seed_animals.php`, `seed_health.php`, `seed_adoptions.php`, `seed_messages.php`, `seed_notifications.php`, `seed_elearning.php`.

Both are **idempotent** — safe to re-run. To wipe the database and start over:

```bat
php bin\migrate.php --fresh --yes
php seeders\seed_users.php
```

`--fresh` drops every table, then re-applies all migrations. `--yes` is required so a wipe cannot happen by accident.

### Step 11 — Start the server
One command from the repo root starts everything — API and pages on the same origin:
```bat
php -S 127.0.0.1:8000 -t public public\index.php
```
Keep this terminal open. Verify it is up by hitting the login endpoint:
```bat
curl -X POST http://127.0.0.1:8000/api/v1/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"admin@furescue.local\",\"password\":\"Password123!\"}"
```
A successful response returns `{"success":true,"data":{...,"tokens":{"access_token":"...","refresh_token":"..."}}}` — the browser app stores `data.tokens.access_token`.

To call an authenticated endpoint from the command line, copy the `access_token` value into a Bearer header:

```bat
curl http://127.0.0.1:8000/api/v1/users/me ^
  -H "Authorization: Bearer <tokens.access_token>"
```

### Step 12 — Build the CSS
In a **second** terminal, from the repo root:
```bat
npm install
npm run build
```
`npm run build` copies `clsx` / `tailwind-merge` / `cva` into `public/assets/js/vendor/`, rebuilds the Lucide subset, and compiles `public/assets/css/input.css` → `public/assets/css/style.css`. Use `npm run watch` if you will edit styles.

### Step 13 — Open the system
With the server from Step 11 running, open in your browser:

| Page | URL |
|------|-----|
| Landing page | http://127.0.0.1:8000/ |
| Login / Register | http://127.0.0.1:8000/auth/login.php |
| **Admin dashboard** | http://127.0.0.1:8000/admin/ |

The pages call the API same-origin at `/api/v1`, so there is no second port or separate frontend server to start.

---

## 3. Demo accounts

All seeded accounts use the password **`Password123!`**:

| Role | Email | Notes |
|------|-------|-------|
| Admin | `admin@furescue.local` | Full dashboard access |
| Rescuer (active) | `rescuer@furescue.local` … `rescuer5@furescue.local` | On-duty |
| Rescuer (pending) | `rescuer6@furescue.local`, `rescuer7@furescue.local` | Pending approval |
| Resident | `juan@furescue.local`, `maria@furescue.local`, `ana@furescue.local`, `pedro@furescue.local`, `rosa@furescue.local`, `miguel@furescue.local` | |

---

## 4. Common problems & fixes

| Problem | Fix |
|---------|-----|
| `Cannot connect to the database` on migrate/seed | Check `.env` DB_* values, that MySQL is running, and the DB/user were created in Step 6. |
| `PHP Warning: Module "mysqli" is already loaded` | Harmless — appears on some setups, ignore it. |
| API returns `500 SERVER_ERROR` | Check `.env` secret values are set; run `php bin\migrate.php` again. |
| Map tiles blank | Requires internet access (Leaflet + OSM tiles load from CDN). |
| Styling looks unstyled | Run `npm run build` from the repo root so `public/assets/css/style.css` exists. |
| `composer` not recognized | Reinstall Composer and open a new terminal. |
| Port 8000 already in use | Change the port in the Step 11 command and use that same port in the Step 13 URLs. The browser code calls `/api/v1` relative to the page origin, so no JS changes are needed. |

---

## 5. Useful commands (quick reference)

All from the repo root:

```bat
composer install
php bin\migrate.php
php bin\migrate.php --fresh --yes
php seeders\seed_users.php
php seeders\seed.php
php -S 127.0.0.1:8000 -t public public\index.php
php vendor\phpunit\phpunit\phpunit
php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml.dist
bin\serve-test.bat
npx playwright test

npm install
npm run build      :: vendor ESM + Lucide subset + Tailwind
npm run watch      :: auto recompile while editing
```

## 6. Tests

The default suite is still in-process PHPUnit against **in-memory SQLite** (`Router::dispatch()`). That is what `composer test` runs. It does not need MySQL or `php -S`.

Enable `extension=pdo_sqlite` and `extension=curl` in `php.ini` (listed in Step 1).

```bat
php vendor\phpunit\phpunit\phpunit
php vendor\phpunit\phpunit\phpunit --filter Api
```

On-demand layers (dedicated `furescue_test` database, never the demo `furescue` schema):

```bat
copy .env.test.example .env.test
php bin\create-test-db.php
php bin\reset-test-db.php
php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml.dist
bin\serve-test.bat
php vendor\phpunit\phpunit\phpunit -c phpunit.live.xml.dist
npx playwright test
```

If `create-test-db.php` is denied, create the schema as a MySQL admin first:

```sql
CREATE DATABASE IF NOT EXISTS furescue_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON furescue_test.* TO 'furescue'@'localhost';
GRANT ALL PRIVILEGES ON furescue_test.* TO 'furescue'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Flutter unit/widget tests (no phone): `cd d:\pepot\furescue-app\app` then `flutter test`. Device `integration_test` is phone-only.

Full pyramid, commands, and the manual GPS / camera / Google / 3D checklist: **[TESTING.md](TESTING.md)**.
