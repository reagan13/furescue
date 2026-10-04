# Quality hardening — handover prompt

Paste the block below into a **new Cursor Agent chat**. Open both `D:\pepot\furescue` and `D:\pepot\furescue-app`. Do not open or edit `D:\pepot\marinesee`.

This **is** the task. After the model reads the plan, it starts **P1** unless you change the `Implement` line.

Do not treat `docs/technical/FEATURES.md` or `docs/technical/ARCHITECTURE_AUDIT.md` as live.

---

```
You are implementing the FurEscue quality-hardening program. Not new features.

Two repos. One product. One API.

| Repo | Path | What it is |
|------|------|------------|
| Web + API | D:\pepot\furescue | Source of product truth. Vanilla PHP 8.1+ REST API + PHP-rendered web UI. |
| Android client | D:\pepot\furescue-app | Flutter client (resident + rescuer only). Not a second backend. |
| MarineSee | D:\pepot\marinesee | Unrelated. Do not edit. |

If web docs and mobile docs disagree, web wins — except Flutter / physical-phone rules, which only exist in furescue-app.

## Implement

Default: the full program in QUALITY_HARDENING.md, one sub-plan at a time, in this order:

P1 → P3 (parallel OK) → P2 → P4 → P5 → P6 → P7 → P8

Start with P1 now. Finish its “Done when”. Run its verify commands. Then start the next unfinished sub-plan. Do not merge P8 into a correctness plan. Do not skip ahead.

If the user names a single ID (e.g. “only P3”), do that ID only and stop.

## Read first (mandatory)

1. D:\pepot\furescue\docs\plans\QUALITY_HARDENING.md   ← the spec. Follow it.
2. D:\pepot\furescue\AGENTS.md
3. D:\pepot\furescue\docs\technical\WORKFLOWS.md
4. D:\pepot\furescue\docs\technical\TESTING.md
5. D:\pepot\furescue\src\Http\Response.php
6. D:\pepot\furescue\src\Http\Routes  (live endpoints — do not copy the list elsewhere)

When the current sub-plan touches Flutter, also read:

7. D:\pepot\furescue-app\AGENTS.md
8. D:\pepot\furescue-app\docs\01-mobile.md
9. D:\pepot\furescue-app\docs\03-handover.md

Trust QUALITY_HARDENING.md + routes + WORKFLOWS over FEATURES.md.

## What “correct” means (do not weaken)

- 500s: generic SERVER_ERROR message to the client. File paths, SQL, and $e->getMessage() go to the server log only.
- Domain errors: typed exception with code + message + httpStatus → { success, data, error: { code, message } }. No RuntimeException('NOT_FOUND') string codes.
- 403 / pending: human copy. Do not leak permission slugs or raw account_status in error.message. Keep existing error codes stable so the phone can still map them (ACCOUNT_PENDING, FORBIDDEN, INVALID_CREDENTIALS, EMAIL_TAKEN, …).
- Phone: every failed or malformed API response is ApiException. Screens never see a raw FormatException.
- Access: listing show uses the same ownership / permission rule as listing index.
- Resolve stays admin-only on the API. Phone uploads proof; City Vet resolves on web.
- Integration: P5 is the required full IPO (in-process PHPUnit). P6 Playwright and P7 phone are UI smokes on top. GPS / camera / Google / 3D stay manual (TESTING.md).

## Sub-plans (do not invent extra ones)

| ID | Repo | Job |
|----|------|-----|
| P1 | web | Safe API errors: public/index.php handlers, UserProfileController, AnimalProfileController, PermissionMiddleware, ACCOUNT_PENDING copy |
| P2 | web | DomainException; CaseWorkflowException + UserAdminException extend it; ResidentListingService stops throwing RuntimeException string codes |
| P3 | app | ApiClient + asJsonMap → ApiException BAD_RESPONSE; secondary catch (_) {} becomes a visible snack/note |
| P4 | web | Listing show scoped; WORKFLOWS resolve wording; FEATURES.md stale banner; app 03-handover duty line |
| P5 | web | tests/Api/LifecycleApiTest.php — one IPO walk including rescuer resolve 403 then admin resolve |
| P6 | web | e2e/ipo.spec.ts — mocked geolocation + fixture files, not a real camera. serve-test + furescue_test only |
| P7 | app | integration_test deepen: apply + accept. MemorySessionStore. No GPS/camera automation |
| P8 | both | Split files over ~300 lines after P5 exists. No behavior change |

## Hard non-goals (do not “helpfully” add)

- Offline report queue, Google Sign-In on device, FCM/push, phone map widget
- Rescuer resolve on the API or phone
- Forgot password, PDF export, payments, AI, iOS, Blender authoring
- A second backend, Firebase, or copying PHP pages into Flutter
- Editing D:\pepot\marinesee
- Deleting URL shims / compatibility stubs
- Changing public URLs or inventing report/case/adoption states

## How to work

1. Identify the current sub-plan. Read its Change / Tests / Done when / Out of scope in QUALITY_HARDENING.md before touching files.
2. Web UI: tokens + existing components. 375 / 768 / 1440 if you touch layout (P4/P6 docs are not layout work).
3. One concern per file. Split only in P8, or if a file you are already editing goes past ~300 lines for a second responsibility.
4. Controllers stay thin; business rules in src/* services.
5. Phone: Bearer JWT only. Do not scrape PHP sessions. Do not call admin-gated routes (verify, assign, animal write, analytics, broadcast).
6. Ground “done” in tool output (PHPUnit, flutter analyze/test, Playwright, device). Do not claim green from memory.

## Verify (after each sub-plan)

Web (P1, P2, P4, P5, P8-web):

cd D:\pepot\furescue
php vendor\phpunit\phpunit\phpunit

After P5 also:

php vendor\phpunit\phpunit\phpunit --filter LifecycleApiTest

App (P3, P7, P8-app):

cd D:\pepot\furescue-app\app
flutter analyze
flutter test

P6: bin\serve-test.bat (furescue_test, never the demo furescue DB) then npx playwright test e2e/ipo.spec.ts
P7: physical phone, adb reverse tcp:8000 tcp:8000, flutter test integration_test -d <phone-id>

If a verify command fails, fix it before starting the next sub-plan.

## After each sub-plan

In chat, report: ID done, files touched, test commands + results, what is next. Then continue unless the user said “only P#”.
```

---

## One-sub-plan variant

Same block, replace the **Implement** section with:

```
Implement only P1 (Safe API errors) from D:\pepot\furescue\docs\plans\QUALITY_HARDENING.md.
Do not start P2–P8 in this session.
```

Swap `P1` for `P3` (app), `P5`, etc.
