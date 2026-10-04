# FurEscue — Feature Inventory (as built)

> **Stale as of August 2026.** Do not treat this inventory as current. Trust `src/Http/Routes` and `docs/technical/WORKFLOWS.md`.

**Date:** August 22, 2026
**Method:** Every claim below was verified by reading the actual source. File paths are relative to the repo root.
**Status legend:**

- ✅ **Fully implemented** — backend + working UI wired end-to-end
- 🟡 **Partially implemented** — works, but with a meaningful missing piece (noted)
- 🎨 **UI-only stub** — frontend exists but no backing endpoint
- ⚙️ **Backend-only** — API/DB implemented, no UI consumes it

> Note: `docs/technical/SYSTEM_REPORT.md` describes a `frontend/` + `backend/` directory split that does **not** exist in this repo. The real layout is: PHP REST API in `src/` (entry `public/index.php`), static admin UI in `public/admin/`, landing/login in `public/includes/` + `public/auth/`, SQL in `migrations/`.

---

## 1. Auth & Session

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| JWT registration API | Registers `resident` or `rescuer`; rescuers start as `account_status = pending`. Validates name/email/password(≥8)/phone/address; rejects duplicate emails (409). Issues access + refresh tokens. | ⚙️ Backend-only | `src/Controllers/AuthController.php` (`register`), `src/Http/Routes/AuthRoutes.php` | `POST /api/v1/auth/register` · `users` |
| JWT login API | Verifies Argon2id/bcrypt hash via `PasswordService`, blocks non-`active` accounts (`ACCOUNT_PENDING`), returns user + tokens. | ⚙️ Backend-only | `AuthController.php` (`login`, `issueTokens`), `src/Auth/JwtService.php`, `src/Auth/PasswordService.php` | `POST /api/v1/auth/login` · `users` |
| Google Sign-In API | Verifies Google ID token (`GoogleAuthService::verifyIdToken`), links by email/google_id or auto-provisions resident. | ⚙️ Backend-only | `AuthController.php` (`google`), `src/Auth/GoogleAuthService.php` | `POST /api/v1/auth/google` |
| Refresh-token API | Exchanges valid refresh token for new access token; re-checks account status. | ⚙️ Backend-only | `AuthController.php` (`refresh`) | `POST /api/v1/auth/refresh` |
| Session-based login page | Server-rendered login form posting to itself; uses `SessionAuth::attemptLogin` (PHP session + `session_regenerate_id`). Redirects admin → `/admin/index.php` (**note:** only `/admin/index.html` exists — redirect target mismatch). "Sign up", "Continue with Google", and "Forgot password?" are dead `#` links — there is **no registration UI anywhere**. | 🟡 Partially implemented | `public/auth/login.php`, `public/auth/js/auth.js`, `src/Auth/SessionAuth.php` | PHP session · `users` |
| Client-side route guard | Admin pages call `requireAuth(["admin"])` on DOMContentLoaded; checks localStorage JWT + role, redirects to login otherwise. 401 responses clear the session. | ✅ Fully implemented | `public/js/lib/api.js` (`requireAuth`, `redirectToLogin`), `public/admin/js/dashboard.js` | — |
| Server-side page guard | `guard.php` redirects visitors without `$_SESSION['user']` to `/auth/login.php`; supports `$requiredRole`. Currently not included by any page [uncertain about intended usage]. | ⚙️ Backend-only | `public/includes/guard.php` | PHP session |

## 2. Users & Rescuer Management

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Current-user profile | Returns the authenticated user from the JWT. | ⚙️ Backend-only | `src/Controllers/UserController.php` (`me`) | `GET /api/v1/users/me` |
| User listing (admin) | Paginated user list with role/status filters; `role=rescuer` branch joins `rescuer_duty_status` to expose `duty_status`. Strips password hashes. | ✅ Fully implemented (admin UI consumes) | `UserController.php` (`index`, `indexRescuers`), `public/admin/js/lib/admin-data.js` (`fetchRescuers`, `fetchRescuerApplicants`, `fetchSuspendedRescuers`) | `GET /api/v1/users` · `users`, `rescuer_duty_status` |
| Profile update | Self or admin; admins may also change `account_status`/`role`. Used by rescuers page to suspend/reactivate. | ✅ Fully implemented (admin UI consumes) | `UserController.php` (`update`), `public/admin/js/pages/rescuers/workflow/actions.js` (`setUserStatus`) | `PATCH /api/v1/users/{id}` |
| Rescuer approval workflow | Admin approves/rejects pending rescuer applicants → writes `rescuer_approvals` row, flips `account_status` to `active`/`rejected`, notifies applicant. UI: Rescuers page actions + dashboard queue buttons. | ✅ Fully implemented | `UserController.php` (`approveRescuer`, `rejectRescuer`, `resolveRescuer`), `public/admin/js/pages/rescuers/workflow/actions.js`, `public/admin/js/pages/dashboard/queue.js`, `public/admin/rescuers.html` | `POST /api/v1/admin/rescuers/{id}/approve` · `POST …/reject` · `users`, `rescuer_approvals`, `notifications` |
| Duty status data | On/off-duty status stored per rescuer; assignment validation requires `on_duty`. No UI found to *toggle* duty status (seeded only) [uncertain whether toggle exists elsewhere]. | 🟡 Partially implemented | `src/Http/Routes/CaseRoutes.php` + `CaseController::assign` (enforcement), `migrations/2024_01_01_000001_users.sql` | `rescuer_duty_status` |

## 3. Reports (Community Incident Reports)

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Create report w/ geotag | Requires `animal_description` (≤2000), `latitude`/`longitude`; validates coordinate ranges (`Validator`); rejects locations outside Mati City bounds (`GeoService::inMatiBounds`, env-configurable bbox). Accepts `photo_urls` as JSON array of URL strings and optional `address_text`. Status starts `pending_verification`. Notifies all admins. | ⚙️ Backend-only (no submit UI) | `src/Controllers/ReportController.php` (`create`), `src/Services/GeoService.php`, `src/Validation/Validator.php` | `POST /api/v1/reports` · `reports` |
| Duplicate detection | SHA-256 content hash of normalized description + rounded coords + day; haversine distance query flags reports within 50 m in the last 24 h (`flagged_duplicate`, responds 409 with created report). | ⚙️ Backend-only | `src/Services/DedupService.php` (`contentHash`, `findDuplicate`), tested in `tests/DedupServiceTest.php` | `reports.content_hash`, `reports.duplicate_of_report_id` |
| Report listing & scoping | Residents see only their own reports (`GET /reports`, `GET /reports/me`); admins see all; filters `status`, `validation_status`; paginated. Single-report view blocks residents from others' reports. | ⚙️ Backend-only | `ReportController.php` (`index`, `mine`, `show`) | `GET /api/v1/reports[/me]/{id}` · `reports` |
| Verify report (admin) | Sets `status=verified`, stamps verifier; auto-creates a rescue case (`open`) if none exists for the report; notifies the reporter. UI: Reports page row action + dashboard queue. | ✅ Fully implemented | `ReportController.php` (`verify`, `setReportStatus`), `public/admin/js/pages/reports/workflow/actions.js`, `public/admin/js/pages/dashboard/queue.js`, `public/admin/reports.html` | `POST /api/v1/reports/{id}/verify` · `reports`, `cases`, `notifications` |
| Dismiss report (admin) | Requires `dismiss_reason`; sets `status=dismissed`; notifies reporter. UI present alongside verify. | ✅ Fully implemented | `ReportController.php` (`dismiss`), same UI files as above | `POST /api/v1/reports/{id}/dismiss` |
| Report photo upload | Only URL strings are accepted for `photo_urls`. There is **no multipart upload endpoint for report photos** and no video support (uploads exist only for animal documents, §6). | 🟡 Partially implemented | `ReportController.php` (`create` — `photo_urls` param), contrast with `src/Controllers/DocumentsController.php` | `reports.photo_urls` (JSON text) |
| Heatmap data feed | Returns lat/lng points for validated reports filtered by status (default `verified`). Consumed by dashboard map. | ✅ Fully implemented | `ReportController.php` (`heatmap`), `GeoService::heatmapPoints`, `admin-data.js` (`fetchHeatmap`) | `GET /api/v1/reports/map/heatmap` · `reports` |

## 4. Cases (Rescue Workflow)

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Case list/detail | Paginated list joined with report description/address and assigned rescuer name; rescuers are scoped to their own assigned cases; status filter. Detail returns entity. | ✅ Fully implemented (admin UI) | `src/Controllers/CaseController.php` (`index`, `show`), `public/admin/js/pages/cases/*`, `public/admin/cases.html`, `public/admin/case-detail.html` | `GET /api/v1/cases[/{id}]` · `cases` |
| Assign rescuer (admin) | Validates rescuer exists, is `active`, and is `on_duty`; sets `assigned_rescuer_id`, status→`assigned`; writes activity log; notifies rescuer. UI dialog lists only on-duty rescuers. | ✅ Fully implemented | `CaseController.php` (`assign`), `public/admin/js/pages/cases/components.js` + `workflow.js`, `case-detail/components/events.js`, `reports/workflow/actions.js` | `POST /api/v1/cases/{id}/assign` |
| Status transitions (staff) | `assigned`/`in_progress`/`resolved` via PATCH; rescuers restricted to their own cases; every change appended to `case_activity_log`; resolving notifies the reporting resident. | ✅ Fully implemented | `CaseController.php` (`updateStatus`, `logActivity`, `activity`) | `PATCH /api/v1/cases/{id}/status` · `GET /api/v1/cases/{id}/activity` |
| Resolution proof photos | Case-detail page has an "add proof" input POSTing to `/cases/{id}/proof` — **no such route exists in the backend**, so the button always fails at runtime. DB column `cases.resolution_photos` exists (migration 7) but no code reads/writes it. | 🎨 UI-only stub | `public/admin/js/pages/case-detail/components/events.js` (`add-proof` handler), `migrations/2024_01_01_000007_case_resolution_photos.sql`; absence verifiable in `src/Http/Routes/CaseRoutes.php` | (missing endpoint) · `cases.resolution_photos` |

## 5. Animals

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Animal CRUD (admin) | Create validates species(dog/cat), breed_type(aspin/puspin), sex; update whitelists fields incl. `photo_urls`, `model_3d_url`, `photo_360_set`; delete is a soft delete (`deleted_at`, migration 8); list filters species/breed/sex/status/source; detail embeds medical record + field-status history. UI: full Animals page grid, create/edit modals, delete confirm. | ✅ Fully implemented | `src/Controllers/AnimalController.php`, `src/Repositories/AnimalRepository.php` (`softDelete`, `findActive`), `public/admin/js/pages/animals/state.js` (`addAnimal`), `components/edit.js`, `workflow.js`, `public/admin/animals.html` | `/api/v1/animals` CRUD · `animals` |
| Field-status logging | Staff logs `rescue_status` (rescued/not_rescued) + `health_status` (healthy/not_healthy) per animal, optionally tied to a case; history endpoint returns descending log. | ⚙️ Backend-only (no dedicated form found; surfaced indirectly in health views) [uncertain if any UI posts field-status] | `AnimalController.php` (`logFieldStatus`, `fieldStatusHistory`) | `/api/v1/animals/{id}/field-status` · `animal_field_status` |
| 3D profiling fields | `animals.model_3d_url` TEXT and `animals.photo_360_set` JSON accepted on create/update. **No UI inputs, no viewer, seeder does not populate them.** | ⚙️ Backend-only | `AnimalController.php` (`create`, `update`), `migrations/2024_01_01_000003_animals_health.sql` | `animals.model_3d_url`, `animals.photo_360_set` |

## 6. Health Monitoring

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Medical record upsert | One record per animal: notes, vaccination_status/details, structured `vaccination_records` (type/date/next-due/status per migration 12), vaccine_protocols, checkup dates, deworming, neutered, weight_kg, temperature_c. Comment documents deliberate choice: records stored exactly as entered, no engine recomputation. | ✅ Fully implemented (admin health-record editor) | `src/Controllers/AnimalMedicalController.php` (`show`, `upsert`), `public/admin/js/pages/health-record/page.js`, `components/health.js` | `PUT /api/v1/animals/{id}/medical` · `animal_medical_records` |
| Composite health-record view | Per-animal payload assembled server-side: overview (health/vaccination/deworming/neutered), timeline history, vaccinations with due-date reminders (red/yellow/blue tones), vitals (weight/temp/latest heart rate), heart-rate history series, documents list, species vaccination reference protocols from `VaccinationEngine::protocolsForSpecies`. Rendered by the Health Record page. | ✅ Fully implemented | `src/Controllers/HealthController.php` (`record`), `src/Services/VaccinationEngine.php`, `public/admin/js/pages/health-record/page.js`, `public/admin/health-record.html` | `GET /api/v1/animals/{id}/health-record` |
| Health records roster | Joined list across animals × medical × latest field-status × latest vital (incl. barangay, has_medical_record flag). Feeds Health Records page KPIs/table/charts. | ✅ Fully implemented | `HealthController.php` (`records`), `public/admin/js/pages/health-records/*`, `public/admin/health-records.html` | `GET /api/v1/health/records` |
| Daily health activity | 400-day daily counts of checkups/treatments/vaccinations for the calendar chart on Health Records. | ✅ Fully implemented | `HealthController.php` (`activity`, `fill`), `admin-data.js` (`fetchHealthActivity`) | `GET /api/v1/health/activity` |
| Vitals ingestion (IoT) | `POST /vitals` authenticated by `X-Device-Key` header vs `DEVICE_API_KEY` env; stores heart_rate_bpm (+optional respiratory_rate_bpm via manual route), source `iot_sensor`. Manual entry (admin) and staff listing endpoints also exist; manual heart-rate entry is wired into the health-record editor. | ✅ Fully implemented | `src/Controllers/VitalsController.php` (`ingest`, `create`, `list`), `health-record/page.js` (`addAnimalVital`) | `POST /api/v1/vitals`, `/api/v1/animals/{id}/vitals` · `vitals_log` |
| Health updates feed | Last 50 field-status events with animal/user joins; rendered in dashboard E-Learning/Health carousel area and used for badge counts. | ✅ Fully implemented | `src/Controllers/AnalyticsController.php` (`healthUpdates`), `dashboard/state.js` | `GET /api/v1/health/updates` |
| Animal documents | Multipart PDF/image upload (ext-whitelisted) to `public/uploads/`, served back by `public/index.php` with correct MIME; rename/type/meta edit; delete removes file + row. Wired into health-record page. | ✅ Fully implemented | `src/Controllers/DocumentsController.php`, `public/index.php` (uploads handler), `health-record/page.js` (`uploadAnimalDocument`, `updateAnimalDocument`, `deleteAnimalDocument`) | `POST /api/v1/animals/{id}/documents`, `PATCH/DELETE /api/v1/documents/{id}` · `animal_documents` |

## 7. Adoption

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Adoption listings (community-postable) | Any authenticated user can post a listing for an existing animal (`pending_review`); admins approve (flips animal to `available`) or reject (requires review_notes); poster notified either way; residents see own listings. The admin Health Record page has a "post for adoption" action calling this endpoint. **No community-facing browse/manage UI exists.** | 🟡 Partially implemented | `src/Controllers/AdoptionListingController.php`, `public/admin/js/pages/health-record/page.js` (`createAdoptionListing`) | `/api/v1/adoption-listings` (+`/approve`,`/reject`) · `adoption_listings`, `animals` |
| Adoption applications | Apply requires animal `available` (409 otherwise); list/show scoped so residents see only their own; admin approve (animal→`adopted`) / reject (reason required, pending-only guard); complete flow stamps `completed_at`. Applicants notified on every decision. UI: dashboard Applications queue (approve/reject). **No community apply/browse UI.** | 🟡 Partially implemented | `src/Controllers/AdoptionController.php` (`apply`, `index`, `show`, `review`, `complete`), `public/admin/js/pages/dashboard/queue.js` (`approveAdoption`, `rejectAdoption`), `admin-data.js` (`fetchAdoptions`) | `/api/v1/adoptions` (+`/approve`,`/reject`,`/complete`) · `adoptions`, `animals` |

## 8. Notifications (in-app)

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Event-driven notification generation | Rows inserted on: report submitted (→admins, via `notifyRole`), report verified/dismissed (→reporter), case assigned (→rescuer), case resolved (→reporter), listing submitted (→admins) / approved-rejected (→poster), adoption applied (→admins) / decided / completed (→applicant), new message (→receiver), rescuer application decision. | ✅ Fully implemented | `src/Services/NotificationService.php`, `src/Controllers/AbstractController.php` (`notifyRole`), plus call sites in Report/Case/Adoption/AdoptionListing/User/Message controllers | `notifications` |
| Notification inbox APIs | Own notifications, `is_read` filter, pagination, mark-one-read (ownership-checked), mark-all-read. | ⚙️ Backend-only for end users; consumed by admin dashboard badge/list | `src/Controllers/NotificationController.php`, `dashboard/state.js` (`fetchNotifications`), `swr.js` (`setNavBadge`) | `GET /api/v1/notifications`, `PATCH …/{id}/read`, `POST …/read-all` |
| Real-time delivery | None found: no WebSockets/SSE, no polling loop, no email/SMS/push channels. Notifications appear on next full page load / manual refresh. | ❌ Not implemented | absence verifiable across `public/js/lib/swr.js`, `public/admin/js/**` | — |
| Admin-initiated broadcasts | No compose/broadcast endpoint or UI — notifications are strictly system-generated from workflow events. | ❌ Not implemented | absence verifiable in `src/Http/Routes/*` | — |

## 9. Messaging

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Context-threaded messaging API | Send requires receiver + related context (`report`/`case`/`adoption` + id) + text; self-messaging blocked; threads fetched by context sorted chronologically; receiver-only read receipts; new-message notification. | ⚙️ Backend-only | `src/Controllers/MessageController.php`, `src/Http/Routes/MessageRoutes.php` | `POST/GET /api/v1/messages`, `PATCH /messages/{id}/read` · `messages` |
| Messaging UI (chat/conversation list) | Does not exist. Sidebar "Messages" link has no navigation target; no frontend file references `/messages`. | ❌ Not implemented | `public/admin/js/layout/sidebar.js` + `app-shell.js` (`NAV_TARGETS` lacks messages); grep of `public/` shows zero `/messages` callers | — |

## 10. E-Learning

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Module management API | Admin creates/updates modules (categories: dog_behavior, cat_behavior, basic_training, general_care; draft/published); residents see published only; unpublished detail blocked for non-admins. | ⚙️ Backend-only | `src/Controllers/ElearningController.php`, `src/Http/Routes/ElearningRoutes.php` | `/api/v1/elearning/modules` CRUD · `elearning_modules` |
| Progress tracking API | Upsert per resident×module: not_started/in_progress/completed + completed_at; personal progress list. | ⚙️ Backend-only | `ElearningController.php` (`progress`, `upsertProgress`) | `/api/v1/elearning/progress` · `elearning_progress` |
| Learning UI / admin CMS | No lesson-viewer or module-editor pages exist. Dashboard shows only published/draft counts and a content carousel fed from `fetchElearning`. Sidebar "E-Learning" link has no target. | 🎨 UI-only stub (counts/carousel only) | `public/admin/js/lib/admin-data.js` (`fetchElearning`), `dashboard/state.js`, `sidebar.js`, `app-shell.js` | — |

## 11. Maps / GIS

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Case-density heatmap | Leaflet 1.9.4 + leaflet.heat on OSM tiles, clamped to Mati City bounds (`maxBounds`, viscosity 1), renders `/reports/map/heatmap` points; heat-intensity Select (low/medium/high presets); expand toggle with `fitBounds`/`invalidateSize`; point counter. | ✅ Fully implemented | `public/admin/js/pages/dashboard/map.js` (`initCaseDensityMap`), `public/admin/index.html` (Leaflet script tags), `dashboard/state.js` (`state.heatmap`) | `GET /reports/map/heatmap` |
| Reverse geocoding proxy | `/geo/reverse` proxies Nominatim (curl, 8 s timeout, CA bundle resolution) returning name/road/full address; consumed by admin location drawer for case addresses. | ✅ Fully implemented | `src/Http/Routes/GeoRoutes.php`, `GeoService::reverseGeocode`, `admin-data.js` (`reverseGeocode`), `public/admin/js/lib/location-drawer.js` | `GET /api/v1/geo/reverse` |
| Case-location drawer/map view | Case detail renders the report's coordinates on a map with reverse-geocoded address. | ✅ Fully implemented | `public/admin/js/pages/case-detail/components/location.js`, `lib/location-drawer.js` | uses `/geo/reverse` |

## 12. Analytics & Dashboards

| Feature | What it actually does | Status | Evidence | API / DB |
|---|---|---|---|---|
| Overview statistics | Counts: reports(+verified), cases(+resolved), animals(+adopted), adoptions pending/completed, rescuers_on_duty (join users), residents. Rendered as dashboard KPI cards. | ✅ Fully implemented | `src/Controllers/AnalyticsController.php` (`overview`), `dashboard/state.js`, `dashboard/components/kpis.js` | `GET /api/v1/analytics/overview` |
| Adoption trends chart | Daily completed-adoption counts (last 30 days) → week bar chart + growth % on dashboard. | ✅ Fully implemented | `AnalyticsController.php` (`adoptionTrends`), `dashboard/helpers.js` (`buildWeekChart`), `dashboard/components/cards.js` | `GET /api/v1/analytics/adoption-trends` |
| Decision queues | Pending reports / rescuer applicants / adoption applications queues with tabbed pagination, empty states, and inline verify/dismiss/approve/reject actions that refresh counts. | ✅ Fully implemented | `public/admin/js/pages/dashboard/queue.js`, `dashboard/components/queues.js` | multiple (see §3–§7) |
| Recent case activity table | Case list rendered as paginated activity table (page size 5). | ✅ Fully implemented | `dashboard/components/activity.js`, `dashboard.js` (`initActivityPagination`) | `GET /api/v1/cases` |
| Exportable reports (CSV/PDF/print) | Not found anywhere. | ❌ Not implemented | absence verifiable across `src/` and `public/admin/js/` | — |

## 13. Frontend Surfaces

| Surface | What it actually is | Status | Evidence |
|---|---|---|---|
| Landing page | Marketing homepage: hero, audiences, features, how-it-works stepper, stats band, CTA. Stat figures ("128 active cases", "64 adopted") are hardcoded decorative values. "Report an Animal" CTAs link to a nonexistent `#report` anchor; no report/adoption functionality on this page; zero API calls. | ✅ Fully implemented (as marketing page) / functional anchors are stubs | `public/includes/homepage.php`, `header.php`, `footer.php`, `site-head.php`, `public/landing/js/landing.js`, `public/landing/components/*.js` |
| Login page | See §1 (session login; dead sign-up/Google/forgot links). | 🟡 | `public/auth/login.php` |
| Admin Command Center | Dashboard shell (fixed sidebar + sticky topbar), KPI cards, heatmap card, adoption-trend chart, two carousels (health/e-learning, clone-loop autoplay), four decision queues, recent activity, notification badge counts cached in sessionStorage. | ✅ Fully implemented | `public/admin/index.html`, `js/dashboard.js`, `layout/{app-shell,sidebar,topbar}.js`, `pages/dashboard/**` |
| Admin Reports page | Filters + KPIs + table, verify/dismiss drawers, assign-rescuer dialog, case status control. | ✅ Fully implemented | `public/admin/reports.html`, `js/pages/reports/**` |
| Admin Cases + Case Detail | Case KPIs/list/map, detail page with info/files/location/actions; proof-photo button broken (see §4). | ✅ (proof photos 🎨 stub) | `public/admin/cases.html`, `case-detail.html`, `js/pages/cases/**`, `js/pages/case-detail/**` |
| Admin Rescuers page | Applicant/active/suspended views, approve/reject/suspend/reactivate actions. | ✅ Fully implemented | `public/admin/rescuers.html`, `js/pages/rescuers/**` |
| Admin Animals page | Grid + create modal (single photo URL), edit flyout, soft-delete, health quick-edit, species breakdown. | ✅ Fully implemented | `public/admin/animals.html`, `js/pages/animals/**` |
| Admin Health Records + Record pages | Roster w/ charts/KPIs/queue; per-animal editor (vaccinations, vitals, documents, reminders, post-for-adoption). | ✅ Fully implemented | `public/admin/health-records.html`, `health-record.html`, `js/pages/health-records/**`, `js/pages/health-record/**` |
| Community member app (report form, my reports, adopt browsing, inbox, e-learning viewer) | **Does not exist.** No resident-facing pages beyond landing+login. | ❌ Not implemented | repo-wide listing of `public/` shows no such pages |
| Admin pages for Listings/Applications/E-Learning/Messages/Notifications/Settings/Analytics | Sidebar entries exist but have no navigation targets and no HTML files. | 🎨 UI-only stub (nav labels) | `sidebar.js` NAV_GROUPS vs `app-shell.js` NAV_TARGETS |

## 14. Platform & Tooling

| Feature | What it actually does | Status | Evidence |
|---|---|---|---|
| REST router & conventions | Single-entry `public/index.php` → `Router` + `RouteLoader`; uniform envelopes `{success,data}` / `{success,error{code,message}}` / paginated `{items,meta}`; global error/exception handlers converting to JSON; serves static files and `/uploads/`. CORS open (`*`) set in `Response` [verify exact header in `src/Http/Response.php`]. | ✅ Fully implemented | `public/index.php`, `src/Http/{Router,RouteLoader,Request,Response}.php` |
| Role middleware | `authMw` (JWT), `adminMw`, `staffMw` (rescuer+admin) applied per-route. | ✅ Fully implemented | `src/Middleware/{AuthMiddleware,RoleMiddleware}.php`, applied throughout `src/Http/Routes/*` |
| Validation library | Fluent validator (required/optional/string/email/minLen/in/latitude/longitude/numeric) used by every controller; unit-tested. | ✅ Fully implemented | `src/Validation/Validator.php`, `tests/ValidatorTest.php` |
| Migrations runner | `bin/migrate.php` applies `migrations/*.sql` tracked in `migrations_log`; 13 migrations creating 17 tables + ALTERs (case open-status, resolution photos, soft delete, barangay, health-record fields, vaccination_records/protocols, birth_date). | ✅ Fully implemented | `bin/migrate.php`, `migrations/2024_01_01_0000*.sql` |
| Idempotent seeder | Guard-existence inserts for users (admin/rescuers/residents), reports, cases, animals, listings, adoptions, e-learning, notifications; demo password `Password123!`. | ✅ Fully implemented | `seeders/seed.php` |
| PHPUnit tests | 6 test classes: Database, DedupService, GeoService, JwtService (token round-trip verified), Validator, Entity\User. No controller/endpoint integration tests. | 🟡 Partially implemented | `tests/*.php`, `phpunit.xml.dist` |
| DB viewer tool | Read-only web SQL browser (SELECT/SHOW/DESCRIBE/EXPLAIN only), table structure + first 100 rows. Dev utility, unauthenticated [note: do not deploy publicly]. | ✅ Fully implemented (dev tool) | `dbtool/index.php` |

---

### Cross-cutting gaps observed while scanning

1. **No community-facing application surface** — the entire resident experience (reporting, adopting, notifications, e-learning, messaging) is API-only.
2. **Broken/stub frontend calls**: `POST /cases/{id}/proof` has no backend route; sidebar links for Listings/Applications/E-Learning/Messages/Notifications go nowhere; topbar search input has no handler; profile menu items are `href="#"`.
3. **Login redirect mismatch**: successful session login redirects admins to `/admin/index.php`, which doesn't exist (only `.html`).
4. **Registration exists only as an API** — no sign-up screen, so the demo accounts come solely from the seeder.
5. **"Real time" is absent** — notifications are synchronous DB rows with no push/poll channel.
