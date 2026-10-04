# Quality hardening — parent plan

**Date:** 22 Sep 2026  
**Repos:** `D:\pepot\furescue` (API + web) · `D:\pepot\furescue-app` (Flutter)  
**Do not edit:** `D:\pepot\marinesee`

This is **not** a new feature program. The study lifecycle already exists. This program makes errors safe, exceptions consistent, access scoped, and the lifecycle **proven** by tests.

Daily suites already pass (504 SQLite, 20 MySQL, 24 Flutter). That is not a full-stack sign-off. There is no one-script walk of report → verify → assign → accept → proof → resolve → health → list → apply → approve.

`docs/technical/FEATURES.md` is stale. Trust this file + `WORKFLOWS.md` + `src/Http/Routes`.

---

## What “correct” means

| Area | Correct behavior |
|------|------------------|
| 500s | Client sees a generic `SERVER_ERROR` message. File paths, SQL, and `$e->getMessage()` stay in the server log. |
| Domain errors | Typed exception with `{ code, message, httpStatus }` → `{ success: false, error: { code, message } }`. No `RuntimeException('NOT_FOUND')` string codes. |
| 403 / pending | Human copy. Do not leak permission slugs (`cases.status_change`) or raw `account_status` in the message. Keep stable **codes** so the phone can still map them. |
| Phone errors | Every failed or malformed API response becomes `ApiException`. Screens never get a raw `FormatException`. |
| Access | List and show use the same ownership / permission rule. |
| Integration | One in-process API test walks the full IPO. Browser and phone add UI smokes on top. GPS / camera / Google / 3D stay **manual** (`TESTING.md`). |

## Do not invent (still OPEN)

Offline report queue · Google Sign-In on device · FCM/push · phone map widget · rescuer resolve on the API · forgot password · PDF export · payments · AI.

---

## Sub-plan map

Build **one sub-plan per session**. Do not merge P8 (file splits) into a correctness plan.

| ID | Title | Repo | Size | Depends on |
|----|-------|------|------|------------|
| [P1](#p1--safe-api-errors) | Safe API errors | web | S | — |
| [P2](#p2--typed-domain-exceptions) | Typed domain exceptions | web | M | P1 |
| [P3](#p3--flutter-error-contract) | Flutter error contract | app | S | — (P1 nicer, not required) |
| [P4](#p4--access-and-docs-alignment) | Access + docs | web | S | — |
| [P5](#p5--ipo-api-lifecycle-test) | IPO API lifecycle test | web | M | P1, P2 |
| [P6](#p6--playwright-ipo-smoke) | Playwright IPO smoke | web | M | P5 |
| [P7](#p7--phone-integration-deepen) | Phone integration deepen | app | M | P3, P5 |
| [P8](#p8--split-oversize-files) | Split oversize files | both | M | P5 (tests first) |

**Suggested order:** P1 + P3 (can be parallel) → P2 → P4 → P5 → P6 / P7 → P8 last.

P1 is the first thing to implement if you only do one.

---

## P1 — Safe API errors

**Goal:** Uncaught PHP errors and 500 catch blocks never leak internals to the client.

**Repo:** `D:\pepot\furescue`

### Change

1. `public/index.php` error + exception handlers:
   - `error_log` the real message / file / line / exception.
   - `Response::error('SERVER_ERROR', 'Something went wrong. Try again.', 500)`.
   - Never interpolate `$file:$line` or `$e->getMessage()` into the JSON body.
2. Same generic 500 message in:
   - `src/Controllers/UserProfileController.php`
   - `src/Controllers/AnimalProfileController.php`
   - any other `Response::error('SERVER_ERROR', $e->getMessage(), …)` site (grep before finishing).
3. `PermissionMiddleware`: message `You do not have permission to do that.` Keep code `FORBIDDEN`. Permission slug may stay in the log only.
4. `AuthController` + `AuthMiddleware` `ACCOUNT_PENDING`: keep the **code**. Change the message to a fixed string (`This account is waiting for approval.` or `This account is not active.`). Do **not** append `(status: rejected)`.

Do not add new error codes in this plan (that would force a phone change). Flutter already keys off `ACCOUNT_PENDING`.

### Tests

- New or extended test: force a permission miss → 403 + new copy, no `cases.status_change` in `error.message`.
- Pending login still `ACCOUNT_PENDING` / 403; message has no `status:`.
- If a 500 path is testable (upload store failure), assert message is the generic string and does not contain `D:\` or `.php:`.

### Done when

- Grep of `src/` + `public/index.php` shows no `$e->getMessage()` inside `Response::error`.
- `php vendor\phpunit\phpunit\phpunit` still green.

### Out of scope

New exception class (P2). Flutter mapping (P3).

---

## P2 — Typed domain exceptions

**Goal:** One exception type for expected domain failures. Controllers map it once.

**Repo:** `D:\pepot\furescue`  
**Depends on:** P1 (so 500 fallback is already safe).

### Change

1. Add `src/Http/DomainException.php` (code + message + httpStatus). Keep it small.
2. Make `CaseWorkflowException` and `UserAdminException` extend it (or delete the twins and throw `DomainException` directly — prefer extend so existing `catch` sites stay obvious for one commit, then collapse).
3. Replace `ResidentListingService` `throw new \RuntimeException('NOT_FOUND')` (and `NOT_HEALTH_READY`, `LISTING_EXISTS`) with `DomainException` using the **same codes and HTTP statuses** the controller already emits.
4. `AdoptionListingController::rejectListingError` becomes `catch (DomainException $e)` → `Response::error($e->errorCode(), $e->getMessage(), $e->httpStatus())`.
5. Optional in this plan (only if it stays small): move `CaseController::assign` duty/status checks into `CaseWorkflowService` so assign matches accept/decline/proof.

Do **not** change public error codes. Phone and existing PHPUnit assert those strings.

### Tests

- Existing `ListingsApiTest` / `CasesApiTest` / `UsersApiTest` must stay green with the same codes.
- Add one unit test that `DomainException` carries code + status.

### Done when

- Grep `throw new \RuntimeException('NOT_FOUND')` (and sibling string codes) in `src/Services` is empty.
- Daily PHPUnit green.

### Out of scope

File-length splits (P8). New product errors.

---

## P3 — Flutter error contract

**Goal:** The app never surfaces a raw `FormatException` or a silent failure the user needed to see.

**Repo:** `D:\pepot\furescue-app`  
**Can start in parallel with P1.**

### Change

1. `ApiClient.get` / `post` / `patch` / `postMultipart`: if `requireMap()` / `requireList()` throws `FormatException`, rethrow `ApiException(code: 'BAD_RESPONSE', message: 'Unexpected response from the API.', status: …)`.
2. `asJsonMap` in `lib/api/json_map.dart`: throw `ApiException` (same code) instead of `FormatException`, **or** add `requireJsonMap` next to it and switch call sites. Prefer one helper so screens keep `on ApiException`.
3. Secondary fetches that use `catch (_) {}` (`case_detail_screen` activity / report merge, `message_thread` read, `notifications` mark-read extras):
   - Keep the screen up.
   - Show a short snack or inline note when the extra fetch fails (`Could not load activity.`).
   - Do not hide the **primary** load error.

### Tests

- `test/api/api_client_test.dart`: success HTTP + non-object `data` → `ApiException` `BAD_RESPONSE`.
- `test/api/envelope_test.dart`: keep current parse tests; add the throw path.
- Optional widget: reports list already has error+retry; do not expand into a screen-test marathon here.

### Done when

- `flutter analyze` clean, `flutter test` green.
- Grep `throw const FormatException` in `lib/api` is gone or only used internally and caught.

### Out of scope

Video picker, map widget, FCM, Lucide icons, notification deep links (product polish, not this program).

---

## P4 — Access and docs alignment

**Goal:** Show matches list. Paper walk matches the API.

**Repo:** `D:\pepot\furescue`  
**Can start anytime.** Small. Do not mix with P2 unless you are already in that controller.

### Change

1. `AdoptionListingController::show`:
   - 404 if missing.
   - If the caller lacks `adoptions.read` **and** is not `posted_by`, 403 `FORBIDDEN` / `Not your listing`.
   - Same rule as `index`.
2. Docs only:
   - `docs/technical/WORKFLOWS.md` §3 and the case state note: rescuer uploads proof; **admin resolves** on `/admin/cases/case-detail.php`.
   - `docs/technical/FEATURES.md`: banner at the top — stale as of Aug 2026; trust routes + WORKFLOWS. Do not rewrite the whole inventory.
   - `D:\pepot\furescue-app\docs\03-handover.md`: move “duty from GET /users/me” into **Already done**.

### Tests

- `ListingsApiTest`: resident A cannot `GET /adoption-listings/{id}` of resident B; admin can; owner can.

### Done when

- PHPUnit listings tests cover the three roles.
- Workflow text no longer says the rescuer resolves.

### Out of scope

Changing who may resolve (product stays admin-only).

---

## P5 — IPO API lifecycle test

**Goal:** One automated test is the source of truth for the paper walk.

**Repo:** `D:\pepot\furescue`  
**Depends on:** P1, P2 (stable errors).  
**This is the “full integration” that does not need a browser or phone.**

### Change

Add `tests/Api/LifecycleApiTest.php` (SQLite, daily suite) that, in **one** test method (or a short ordered class):

1. Resident `POST /api/v1/reports` (Mati fixture lat/lng + description).
2. Admin `POST /reports/{id}/verify` → case exists `open`.
3. Rescuer `PATCH /rescuers/{id}/duty` `on_duty`.
4. Admin `POST /cases/{id}/assign`.
5. Rescuer `POST /cases/{id}/accept` → `in_progress`.
6. Rescuer `POST /cases/{id}/proof` with a temp PNG via `$_FILES` (copy the pattern in `CasesApiTest`).
7. Rescuer `POST /cases/{id}/resolve` → **403** (lock the product rule).
8. Admin `POST /cases/{id}/resolve` → `resolved`.
9. Admin creates animal tied to the case + vaccination row + vital (use existing seed helpers / health endpoints).
10. Assert listing/adoption eligibility; admin lists available **or** resident applies on an eligible animal.
11. Resident `POST /adoptions` → admin approve → complete.
12. Assert notifications were written for reporter / rescuer / applicant (spot-check types, not copy).

Reuse `ApiTestCase` seeders and `assertOk` / `assertError`. No new HTTP client.

Optional: one `@group mysql` method in `tests/Mysql/LifecycleMysqlTest.php` that calls the same private helper if extraction stays thin. Skip if it would duplicate a lot — SQLite lifecycle is the required one.

### Done when

- `php vendor\phpunit\phpunit\phpunit --filter LifecycleApiTest` green.
- Full daily suite still green.
- A future status-machine change fails this test.

### Out of scope

Playwright (P6). Phone (P7). Camera/GPS.

---

## P6 — Playwright IPO smoke

**Goal:** Browser UI can walk the same lifecycle with **fixtures**, not a real camera.

**Repo:** `D:\pepot\furescue`  
**Depends on:** P5 (API path known good).  
**Needs:** `bin\serve-test.bat` + `furescue_test`.

### Change

Add `e2e/ipo.spec.ts` (or extend `admin.spec.ts` only if it stays readable):

1. Resident session → `/report/`.
2. Mock geolocation to Mati (`browser.context.grantPermissions` + `setGeolocation`). Attach a **fixture** image, not getUserMedia.
3. Admin verify + assign (reuse selectors from `e2e/admin.spec.ts`).
4. Rescuer `/cases/` → on duty → accept → proof from a fixture file.
5. Admin resolve on case detail.
6. If health-ready + available animal already exists from seed, resident apply + admin approve. If creating an animal in-browser is too brittle, stop after resolve and assert the API lifecycle test owns steps 9–12.

Update `TESTING.md`: Playwright IPO is automated with mocked geo + fixture files. **Phone GPS and real camera stay manual.**

### Done when

- `npx playwright test e2e/ipo.spec.ts` passes against serve-test.
- Existing `auth` / `resident` / `rescuer` / `admin` specs still pass.

### Out of scope

Pixel-perfect 375/768/1440 in this spec (page-map specs already hit 375 + 1440). Real Google OAuth.

---

## P7 — Phone integration deepen

**Goal:** Device tests go past “tabs exist.”

**Repo:** `D:\pepot\furescue-app`  
**Depends on:** P3, P5 (API fixtures / seed data).  
**Needs:** physical phone, `adb reverse`, API on `0.0.0.0:8000` pointed at **`furescue_test`** if the test mutates data.

### Change

New files under `app/integration_test/` (keep existing smokes):

1. **Resident apply:** login juan → Browse → open first available animal → apply (or skip if gallery empty — fail with a clear message that seed must include an available animal).
2. **Rescuer case action:** login rescuer → open an **assigned** case if present → accept. Do not require camera; skip proof if no fixture injection is clean.
3. Keep `MemorySessionStore`. Do not hit the Android keystore.

Do **not** automate GPS report submit or camera proof (`TESTING.md` manual list).

### Done when

- `flutter test integration_test -d <phone-id>` passes on a device with adb reverse.
- Existing auth / resident / rescuer smokes still pass.

### Out of scope

Offline queue, FCM, map, in-app 3D, video picker.

---

## P8 — Split oversize files

**Goal:** Stay under the ~300-line / one-concern rule **after** tests exist.

**Depends on:** P5 at minimum (so splits cannot silently break the lifecycle).

| File | Lines (22 Sep 2026) | Split toward |
|------|---------------------|--------------|
| `src/Controllers/HealthController.php` | 359 | Roster vs record vs activity services |
| `src/Services/VaccinationEngine.php` | 336 | Protocols vs due-date helpers |
| `src/Controllers/AnimalController.php` | 300 | Keep if P2 assign move already thinned it |
| `src/Controllers/UserController.php` | 300 | Profile vs admin vs duty already have services — controller stay thin |
| `public/admin/js/queue.js` | 433 | One file per queue type |
| `app/lib/screens/report_form_screen.dart` | 307 | Locate / photos / submit mixins or widgets |

No behavior change. Re-run P5 + `flutter test` after each split.

### Out of scope

Folder-architecture shims. New CSS. New screens.

---

## Verification (per sub-plan)

Web:

```bat
cd D:\pepot\furescue
php vendor\phpunit\phpunit\phpunit
php vendor\phpunit\phpunit\phpunit --filter LifecycleApiTest
```

App:

```bat
cd D:\pepot\furescue-app\app
flutter analyze
flutter test
```

Live / phone only when the sub-plan says so, always on `furescue_test`, never the demo `furescue` database.

---

## Handover prompt

Paste-ready Agent prompt (full program or one ID): [QUALITY_HARDENING_PROMPT.md](QUALITY_HARDENING_PROMPT.md).

Open both repos. Do not edit MarineSee. The prompt starts at **P1** and walks P1 → P3 → P2 → P4 → P5 → P6 → P7 → P8, verifying after each. To do one ID only, use the variant at the bottom of that file.
