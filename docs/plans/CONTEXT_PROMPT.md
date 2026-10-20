# FurEscue — context prompt

Paste the block below into a **new Cursor Agent chat**. Open both `D:\pepot\furescue` (product + API + web) and `D:\pepot\furescue-app` (Flutter client) when the work might touch either. Do not open or edit `D:\pepot\marinesee`.

This is orientation, not a task. After the model reads it, give the actual job in the next message.

Do not treat `docs/technical/FEATURES.md` or `docs/technical/ARCHITECTURE_AUDIT.md` as live. Those lag the code.

---

```
You are working on FurEscue (also spelled FuRescue / FURescue; marketing name “Spot”).

FurEscue is a Mati City animal rescue and adoption system for the City Veterinarian’s Office. It connects residents, rescuers, and admin on one platform: geotagged stray/injured/abandoned reports, case assignment, health records, adoption, in-app messages, notifications, e-learning, and welfare analytics.

There are two repos. One product. One API.

| Repo | Path | What it is |
|------|------|------------|
| Web + API | D:\pepot\furescue | Source of product truth. Vanilla PHP 8.1+ REST API + PHP-rendered web UI. |
| Android client | D:\pepot\furescue-app | Flutter client (resident + rescuer only). Not a second backend. |
| MarineSee | D:\pepot\marinesee | Unrelated. Do not edit. |

If web docs and mobile docs disagree, web wins — except Flutter / physical-phone rules, which only exist in furescue-app.

## Read first (mandatory before changing product behavior)

Web (canonical “what”):

1. D:\pepot\furescue\AGENTS.md
2. D:\pepot\furescue\docs\study\OBJECTIVES.md
3. D:\pepot\furescue\docs\study\SIGNIFICANCE_AND_SCOPE.md
4. D:\pepot\furescue\docs\study\CONCEPTUAL_FRAMEWORK.md
5. D:\pepot\furescue\docs\technical\WORKFLOWS.md
6. D:\pepot\furescue\src\Http\Routes  (live endpoints — do not copy this list elsewhere)

Then only what the task needs:

- Envelope / CORS: src\Http\Response.php  `{ success, data, error }`
- JWT: src\Auth\JwtService.php
- Tokens / type: public\assets\css\tokens.css + docs\study\DESIGN_SYSTEM.md
- Run / seed: docs\technical\HOW_TO_RUN.md
- Bring-up and pass check (web through phone): docs\technical\HANDOVER_RUN.md
- Tests: docs\technical\TESTING.md
- Folder / CSS cascade: docs\technical\FOLDER_ARCHITECTURE.md
- URL freeze: docs\technical\FOLDER_OVERHAUL_SPEC.md §4

Phone (only when touching Flutter):

1. D:\pepot\furescue-app\AGENTS.md
2. D:\pepot\furescue-app\docs\01-mobile.md
3. D:\pepot\furescue-app\docs\02-how-to-run.md
4. D:\pepot\furescue-app\docs\03-handover.md
5. D:\pepot\furescue-app\docs\04-handover-run.md   (seed, serve, phone check)

Labels in mobile docs: STUDY = web requirement; IMPLEMENTATION = phone-only decision; OPEN = do not invent in code.

## Study (do not invent past this)

Ten objectives: (1) adoption portal, (2) community reporting, (3) geotag heatmap, (4) notifications, (5) analytics, (6) in-app messaging, (7) community post-for-adoption, (8) e-learning, (9) 3D animal profiles, (10) health monitoring (medical history, vaccines, heart rate).

IPO: Input (reports, applications, health, pins) → Process (Mati bounds, dedup, verify, assign, duty check, health-ready listing, approve/reject) → Output (heatmap, cases, gallery, analytics, notifications, 3D).

Hard scope:

- Mati City only. Server clamps reports to MATI_LAT_* / MATI_LNG_* bbox.
- Dogs and cats only; Aspin / Puspin.
- Roles: resident, rescuer (staff), admin (City Vet). Rescuer signups start pending until admin approves.
- Cross-platform = responsive web + this Flutter app. Not iOS v1. Not a second stack.

Hard non-goals (study limitations — do not “helpfully” add):

- Other municipalities
- Payments / donations / adoption fees
- AI recognition or health diagnosis
- Predictive / AI heatmap forecasting
- Messenger / WhatsApp / SMS
- Biometrics / blockchain
- Authoring 3D in-repo (no Blender). Playback of model_3d_url / a 360 set, or a placeholder.

## Roles and pages

Residents never see My Cases. Rescuers never see Report / Browse / My Adoptions / My Listings. Admins use /admin/… on web only.

Demo password for every seeded account: Password123!

| Role | Login | Home |
|------|--------|------|
| Resident | juan@furescue.local | /reports/ |
| Rescuer | rescuer@furescue.local | /cases/ |
| Admin | admin@furescue.local | /admin/ |
| Pending rescuer | rescuer6@furescue.local | blocked until approved |

Resident web: /report/ /reports/ /animals/ /adoptions/ /listings/ /learning/ /messages/ /notifications/ /account/
Rescuer web: /cases/ plus shared learning / messages / notifications / account
Admin web: /admin/ (heatmap), reports, cases, rescuers, animals, health-records, listings, applications, elearning, messages, notifications, analytics
Public: /  /auth/login.php  /auth/signup.php

Mobile v1 maps those resident + rescuer screens. If an admin JWT lands in the app, show “use the web console” and stop. Do not call admin-gated routes from the phone (verify, assign, animal write, analytics, broadcast).

## Status machines (do not invent extra states)

Report: pending_verification → verified | dismissed. Verify creates a case.

Case: open → assigned (admin assigns an on-duty rescuer) → in_progress (rescuer accepts) | back to open (decline) → resolved (proof + resolve).

Resolve is admin-only on the API (cases.status_change / CaseWorkflowService). Phone uploads proof; City Vet resolves on web.

Animal listing: not_listed → available (needs health-ready: ≥1 vaccination row and ≥1 vital) → pending (application) → adopted | back to available if rejected.

Community listing: pending_review → approved | rejected.

E-learning: draft | published; progress not_started → completed.

Adoption application: pending → approved | rejected → completed.

Main lifecycle to keep in your head:

Resident submits geotagged report + photos
→ admin verifies (creates case) and assigns on-duty rescuer
→ rescuer accepts, uploads proof
→ admin resolves, registers animal, records health
→ animal listed available
→ resident applies
→ admin approves / rejects / completes

If a screen is empty, the previous status was never reached (e.g. assign dialog empty → no rescuer is on duty).

## Architecture

One PHP server serves API + pages:

php -S 127.0.0.1:8000 -t public public\index.php

public\index.php serves real files under public/, special-cases /uploads/, else App\Http\Router. All API paths start /api/v1.

Web stack:

- PHP 8.1+ , custom router, PDO + MySQL 8.0.13+ (schema uses DEFAULT (UUID()))
- JWT Bearer for API (AuthMiddleware) + role/permission gates (RoleMiddleware / PermissionMiddleware)
- Web pages also use PHP $_SESSION after /auth/login.php. The phone does not — phone uses POST /api/v1/auth/login + refresh only. Never scrape cookies.
- Exception: POST /api/v1/vitals uses X-Device-Key vs DEVICE_API_KEY (IoT), not JWT
- Frontend: PHP-rendered pages + ES-module islands. Thin bootstraps in public/<page>/index.php; markup in views/; shared chrome in views/layouts and views/components (require views_path() from views/path.php)
- CSS: tokens in public/assets/css/tokens.css; edit input.css (and imports); compiled style.css is git-tracked — npm run build after token/input/tailwind.config.js changes
- Icons: Lucide only via /assets/js/vendor/lucide.js. After a new data-lucide name, npm run build:lucide
- Maps: Leaflet + leaflet.heat on web (verified reports only). Phone map widget is OPEN
- Notifications: in-app + EventSource on web. No FCM/push unless study + handover say so
- Dedup: DEDUP_* env; content hash + nearby reports
- DB_DRIVER defaults to pgsql in src/Database.php if unset — always have .env with DB_DRIVER=mysql
- Migrations are plain SQL in migrations/; never edit applied files; add YYYY_MM_DD_NNNNNN_name.sql
- dbtool/ is a standalone SQL viewer, not part of the app
- FEATURES.md (2026-08-22) and ARCHITECTURE_AUDIT.md describe older trees/gaps. Trust routes + WORKFLOWS + code.

Mobile stack:

- Flutter Android, applicationId com.furescue.app, minSdk 26
- Physical phone only. Never treat the emulator as the default device
- Calls the same /api/v1. Envelope { success, data, error }. Store access + refresh tokens
- Default API base is http://127.0.0.1:8000 via adb reverse. Wi-Fi: --dart-define=API_BASE_URL=http://<lan-ip>:8000
- Tokens in app/lib/theme/tokens.dart ported from web. Change tokens on web first
- No Firebase, no second REST mock as source of truth, no PHP pages copied into Flutter

## Hard engineering rules

Web UI:

- Every layout change must work at 375 / 768 / 1440. Mobile-first. No fixed-width columns. Zero horizontal overflow at 375px
- Use design tokens and existing components (.input, .toast, .loader-overlay, .logo-mark, .badge-icon, .kpi-card). No ad-hoc hex/radius/font. New reusable style → tokens.css / input.css + tailwind.config.js
- One concern per file. Split past ~300 lines. No new monoliths
- Keep public URLs stable (FOLDER_OVERHAUL_SPEC §4). Leftover 302 stubs and /css /js/lib shims stay until inbound grep is empty AND the user confirms delete
- Controllers stay thin; business rules in src/* services; DB in repositories/PDO helpers
- After CSS/JS token work: npm run build. Tailwind content globs: ./public/**/*.{php,js,html} and ./views/**/*.{php,js}

Mobile:

- Flutter only. No Kotlin / React Native second app
- Do not call admin-gated routes
- Do not invent report/case/adoption states
- Screens stay out of API / session / theme files

Both:

- Do not expand scope past the study
- Do not add a second backend
- Do not author .blend files
- Ground “done” in tool output (tests, routes, browser/phone). Do not claim screens work from memory

## Commands

Web (from D:\pepot\furescue):

copy .env.example .env
composer install
php bin\migrate.php
php seeders\seed.php
npm install
npm run build
php -S 127.0.0.1:8000 -t public public\index.php

php vendor\phpunit\phpunit\phpunit
php vendor\phpunit\phpunit\phpunit --filter DedupServiceTest
php vendor\phpunit\phpunit\phpunit -c phpunit.mysql.xml.dist
bin\serve-test.bat
npx playwright test

MySQL / live HTTP / Playwright use furescue_test + .env.test — never the demo furescue database.

Phone + local API:

php -S 0.0.0.0:8000 -t public public\index.php
adb reverse tcp:8000 tcp:8000
cd D:\pepot\furescue-app\app
flutter pub get
flutter devices
flutter run
flutter test
flutter analyze

## Open (do not decide in code)

- Offline report queue (study allows async on bad network)
- Google Sign-In on device (POST /api/v1/auth/google exists; device wiring OPEN)
- Push (FCM) vs poll
- Phone map widget (flutter_map + OSM vs Google Maps)
- Whether resident web stays long-term or mobile becomes the community surface (both may coexist)

## How to work

1. Identify which repo owns the change. Product “what” is always furescue. Phone “how” is furescue-app.
2. Read the live route file and WORKFLOWS.md before adding an endpoint, status, or screen.
3. Reuse existing services, views, tokens, and Flutter widgets. Extract a shared piece only when duplication is real.
4. Verify web UI in the browser at 375 / 768 / 1440. Verify phone work on a physical device, not the emulator.
5. Stop and ask before: deleting shims/stubs, adding dependencies, changing a public URL, inventing an OPEN feature, or expanding past the study.

Wait for the user’s task. Do not start a drive-by refactor.
```
