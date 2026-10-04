# AGENTS.md

Vanilla PHP 8.1+ REST API (no framework, custom router, PDO + MySQL) + frontend of PHP-rendered pages with ES-module islands. One server serves both: `php -S 127.0.0.1:8000 -t public public\index.php` from repo root.

The frontend is PHP-rendered pages with ES-module islands. Shared chrome lives in `views/` (`views/layouts/admin.php`, `views/layouts/resident.php`, `views/components/site-head.php`, `views/components/guard.php`). Thin page bootstraps stay under `public/` so URLs do not change. `views/path.php` exposes `views_path()`.

## Responsive frontend (hard rule)

- Every UI/layout change MUST work at all three breakpoints before it counts as done: **375px** mobile, **768px** tablet, **1440px** desktop.
- Write mobile-first: base styles target the smallest screen; enhance upward with `min-width` media queries.
- No fixed-width containers or grid columns. Use fluid sizing: `max-width`, `%`, `clamp()`, `fr`/`minmax()` grid tracks, `flex-wrap`. Zero horizontal overflow at 375px.
- Images/media scale with their container (`max-width: 100%`; never distort). Navigation must remain usable at 375px (collapse to the hamburger/stacked pattern used elsewhere in the repo).
- Verify by actually loading the page from the running server and resizing (devtools device toolbar) at all three widths — assumption is not verification.

## Frontend design system (hard rule)

- **Follow the existing system design first.** All frontend styling must come from the shared design tokens, never ad-hoc values:
  - Colors, radius, shadows, fonts: CSS variables in `:root` / `.dark` in `public/assets/css/tokens.css` (e.g. `--primary`, `--border`, `--radius`, `--shadow-md`, brand colors `--brand-1/2`, palette `paper`/`ink`/`jungle`/`coral`/...), mapped to Tailwind classes in `tailwind.config.js`. Edit the cascade via `public/assets/css/input.css`; the compiled sheet is `public/assets/css/style.css`.
  - Icons: Lucide only (`lucide.createIcons()` via the local `/assets/js/vendor/lucide.js` import map), sized with the existing `.lucide` conventions — never inline SVG or emoji as icons.
  - Shared components/patterns (`.input`, `.toast`, `.loader-overlay`, `.logo-mark`, `.badge-icon`, admin/resident shells) already exist in the `public/assets/css/` cascade and `views/layouts/` / `views/components/` — reuse them instead of restyling from scratch.
- **New reusable style = add it to the system.** If you introduce a new border radius, color, shadow, spacing scale, font, icon set, or component pattern that could be used by other pages, promote it into `public/assets/css/` (token in `tokens.css` and/or a shared class imported from `input.css`) plus a mapping in `tailwind.config.js` if it needs Tailwind utilities — then use the token everywhere. Do not hardcode one-off values in page-level styles.
- Never hardcode raw hex/hsl colors, radii, or font stacks in markup or JS when a token exists; if no suitable token exists, create one first.
- After editing `input.css` or `tailwind.config.js`, run `npm run build` (and remember the Tailwind `content:` globs gotcha below for `.php` files).
- The responsive rule above still applies: any new token/component must be verified at 375px / 768px / 1440px.

## Folder architecture (overhaul)

Target tree and CSS cascade: `docs/technical/FOLDER_ARCHITECTURE.md`.  
Keep-list, URL freeze, workstreams: `docs/technical/FOLDER_OVERHAUL_SPEC.md` (§4 URL freeze).  
Live layout: thin bootstraps in `public/admin/<page>/index.php` (and `public/<resident-page>/`), markup in `views/admin/<page>/` (and `views/<resident-page>/`), shared CSS/JS in `public/assets/`. Compatibility shims still exist (see Gotchas).

## Modular files, no monoliths (hard rule)

- Never bottleneck logic or markup in one long file. One concern per file; split when a file grows past ~300 lines, accumulates a second responsibility, or duplicates markup/logic used elsewhere.
- Frontend: new pages live in their own folder as `public/<page>/index.php` (URL `/page/`; sub-pages are sibling `.php` files in that folder). The public file is a thin bootstrap; markup lives in `views/<page>/` (admin: `views/admin/<page>/`). Page JS is flattened under `public/<page>/js/` — no `js/pages/` nesting — and is referenced with absolute paths (`/page/js/app.js`). Shared markup goes in `views/layouts/` and `views/components/` (`require views_path('…')` from `views/path.php`). Shared JS is `public/assets/js/{lib,components,admin}`; reusable styles become tokens/classes in `public/assets/css/` (`tokens.css`, `input.css` entry + one-concern files).
- Backend: controllers stay thin; business rules belong in `src/*` service classes, DB access in repositories/PDO helpers — not inline in route closures.
- When creating a shared piece (partial, module, class), reuse the existing ones first; only extract new ones when the duplication is real.

## Commands

```bat
composer install
php vendor\phpunit\phpunit\phpunit          :: tests (no composer test script; vendor/bin/phpunit is a bash script, use this on Windows)
php vendor\phpunit\phpunit\phpunit --filter DedupServiceTest   :: single class
php bin\migrate.php                          :: apply migrations/*.sql
php bin\migrate.php --fresh --yes            :: drop all tables, re-apply migrations
php seeders\seed.php                         :: idempotent full demo data (orchestrates domain seeders)
php seeders\seed_users.php                   :: users, roles, and permissions only
php -S 127.0.0.1:8000 -t public public\index.php   :: run everything
php vendor\phpunit\phpunit\phpunit           :: daily SQLite suite
php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml.dist :: MySQL API (@group mysql)
php vendor\phpunit\phpunit\phpunit -c phpunit.live.xml.dist :: HTTP cookie/CORS smoke (needs serve-test)
bin\serve-test.bat                           :: php -S with .env.test (does not overwrite .env)
npx playwright test                          :: browser matrix (needs serve-test)
npm run build                                :: copy vendor ESM + rebuild Lucide subset + compile Tailwind
npm run build:vendor                         :: copy clsx / tailwind-merge / cva into public/assets/js/vendor/
npm run build:lucide                         :: rebuild local Lucide subset after adding icons
```

- Default tests are in-process PHPUnit against SQLite (`php vendor\phpunit\phpunit\phpunit` / `composer test`). They do not need MySQL or `php -S`.
- MySQL / live HTTP / Playwright / Flutter device tests use `furescue_test` and `.env.test`. Commands: `docs/technical/TESTING.md`. Never point those layers at the demo `furescue` database.
- Setup details and demo accounts (`Password123!`): `docs/technical/HOW_TO_RUN.md`.

## Gotchas

- **DB driver defaults to pgsql**: `src/Database.php` falls back to Postgres when `DB_DRIVER` is unset. The schema (e.g. `DEFAULT (UUID())`) requires MySQL 8.0.13+. Always have `.env` (copy `.env.example`) before migrate/serve.
- **Migrations are plain SQL files** in `migrations/`, applied once by filename order and logged in `migrations_log`. Add new ones as `YYYY_MM_DD_NNNNNN_name.sql`; never edit applied files.
- **CSS**: edit `public/assets/css/input.css` (and the files it `@import`s, including `tokens.css`), never the compiled `public/assets/css/style.css` (both are git-tracked; rebuild with `npm run build`). `npm run build` copies `clsx` / `tailwind-merge` / `cva` into `/assets/js/vendor/`, regenerates `/assets/js/vendor/lucide.js`, then runs Tailwind (`-i ./public/assets/css/input.css -o ./public/assets/css/style.css`). Tailwind `content:` in `tailwind.config.js` covers `./public/**/*.{php,js,html}` and `./views/**/*.{php,js}` — classes used only outside those globs get purged on rebuild. Icons stay local: the HTML import map points `lucide` at `/assets/js/vendor/lucide.js`. After adding a new `data-lucide` name, run `npm run build:lucide`.
- **Compatibility shims** (keep until inbound grep is empty **and** the user confirms delete): `/css/style.css` `@import`s `/assets/css/style.css`; `/js/lib/*` re-exports `/assets/js/lib/*`; leftover `public/css/input.css` is a comment-only pointer; `public/includes/*` are one-line `require`s of the `views/` files. Leftover 302 stubs (`public/admin/reports.php`, `animals.php`, `cases.php`, `rescuers.php`, `health-records.php`, `health-record.php`, `case-detail.php`) still redirect — URL freeze: `docs/technical/FOLDER_OVERHAUL_SPEC.md` §4.
- **Frontend vendor JS**: `clsx`, `tailwind-merge`, `class-variance-authority`, and `lucide` are copied/generated into `public/assets/js/vendor/` at build time (`npm run build` / `build:vendor` / `build:lucide`). The HTML import map points at those local files — not esm.sh. Leaflet still loads from CDN, but only when a map is in view. `node_modules` is build-time only.

## Architecture

- Entry point `public/index.php`: serves real files under `public/`, special-cases `/uploads/`, otherwise dispatches to `App\Http\Router`. All API paths start `/api/v1`.
- Routes are registered per-domain in `src/Http/Routes/*Routes.php` and wired via `RouteLoader::register()`. Adding an endpoint = new route line there + controller method; shared deps (PDO, JWT, middleware) are passed in the `$deps` array built in `public/index.php`.
- Responses use the JSON envelope `{ success, data, error }` from `App\Http\Response`.
- Auth: Bearer JWT (`AuthMiddleware`) + role gates (`RoleMiddleware`: `admin` vs staff `rescuer|admin`). Exception: `POST /api/v1/vitals` authenticates with the `X-Device-Key` header against `DEVICE_API_KEY` instead of JWT.
- Geovalidation clamps reports to the Mati City bounding box env vars; report dedup tuning lives in `DEDUP_*` env vars.
- `dbtool/index.php` is a standalone dev SQL viewer, not wired into the app.

## Docs caveat

`docs/technical/ARCHITECTURE_AUDIT.md` still describes the pre-refactor `backend/` + `frontend/` tree and `.html` URLs. Code now lives at the repo root (`src/`, `public/`, etc.) and pages are `.php`. Trust the code over that audit. Onboarding for humans is `README.md` + `docs/technical/HOW_TO_RUN.md`.
