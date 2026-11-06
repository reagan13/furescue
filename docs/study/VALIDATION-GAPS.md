# Validation gaps

## How a row gets closed

Use `.cursor/skills/furescue-thesis-qa/SKILL.md` for one statement, objective, or row. Website proof is PHPUnit, then Playwright against `bin\serve-test.bat`, using `.cursor/skills/webapp-testing/SKILL.md` (wait for `networkidle`, then act) at 375, 768, and 1440. Phone proof is `flutter test` in `furescue-app`. `flutter test integration_test` runs only when a physical phone is attached. If none is attached, the phone side of that row stays unfinished. Update this file in the same change. Do not mark `rater-only` from code. The built-in `php -S` server sends one notification sync and closes; it is not push. The inbox refreshes on the 30-second poll.

QA pass 22 Oct 2026: PHPUnit 521 passed. Playwright’s report walk and the rescuer case list at 375, 768, and 1440 passed, and the case page showed the pin. Flutter test 72 passed. No physical phone was attached, so every phone row below stays unfinished. Rater-only rows were not scored.

Checked 22 Oct 2026 against the web app (`D:\pepot\furescue`) and the Flutter phone app (`D:\pepot\furescue-app`). Statement wording is `FURESCUE-VALIDATION-QUESTIONS.md`. Study aims are `OBJECTIVES.md` and `REQUIREMENTS_SPECIFICATION.md`.

This file is the work list. A high survey score is not available while a row below is `missing` or `unfinished`. Rows marked `rater-only` still need a person on the real site or a physical phone. Code that exists is not a score.

## How to read a row

| Status | Meaning |
|--------|---------|
| `missing` | That role has no screen for the statement. |
| `unfinished` | The path exists and stops short: a stub, a wrong recipient, a file that cannot be played, a seed that never shows the control, or a link that leaves the role’s portal. |
| `rater-only` | The path exists. Speed, looks, learnability, and Wi-Fi versus mobile data still have to be scored by a respondent. |
| `present` | A person in that role can finish the statement today. Listed only when an earlier reading called it a hole and the code does the job. |

Admin screens stay on the website. The phone app stops an admin login. That is not a phone gap.

## Already in place

Do not rebuild these while closing the gaps.

- Resident web and phone: sign up, log in, browse animals, apply to adopt, file a Mati geotagged report with photos, notification inbox, published lessons with a seeded quiz, community listing submit.
- Rescuer web and phone: duty on/off, assigned cases, accept, decline, proof photos, messages to the first City Vet admin, learning.
- Admin web: animal create/edit/delete, verify and dismiss reports, assign an on-duty rescuer, resolve a case, approve/reject/complete adoptions, approve/reject listings, health record (notes, vaccines, weight, temperature, heart rate), heatmap, analytics counts and adoption-trend CSV, broadcast, e-learning publish, 3D file upload, messages to reporters and applicants.
- Phone blocks an admin JWT. Dogs/cats and Aspin/Puspin are enforced on animal records and listings. Payments, Messenger, WhatsApp, and image recognition are absent, which matches the study limits.
- PHPUnit covers the API workflows named in the admin maintainability statement. It does not cover a rescuer map, because that page was never built.

## Fix the form before anyone fills it

The Word file still has copy-paste from other studies. Respondents will score the printed words.

| Printed text | Change |
|--------------|--------|
| Title “Cross-Platfrom” | “Cross-Platform” |
| Consent contact `[contact number]` and unclosed `[group email address` | Real number and group email |
| User items numbered 8 twice and 20 twice | One number per statement |
| User 18 and 19, “rice field information” | The user’s reports, adoptions, and animal records |
| Admin 18, “the AgriVista system” | FuRescue |
| Responder 10, “mobile web application” on Android | The FuRescue Android app (Flutter). The website is the separate responsive site. |

Until those lines change, do not build a rice-field feature or an AgriVista brand.

## Plan

Work in this order. Each item names the statement it closes.

### 1. Rescuer map

Website `present`. Phone `unfinished` until a phone is attached. R-FS-2. R-PE-2 is rater-only once the pin is on screen.

Cases show `address_text` only (`public/cases/js/detail-render.js`, `app/lib/widgets/case_detail_view.dart`). The case list query does not select coordinates. Leaflet already exists on the admin dashboard and on the resident report form.

Add a pin map of the assigned report on `/cases/detail.php` and on the phone case screen. Keep the admin heatmap where it is.

### 2. Message the person the form names

Website `present`. Phone `unfinished` until a phone is attached. U-FS-5, R-FS-5, A-FS-12, objective 6.

`MessageController::staffReceiver` always picks the oldest active admin. A resident or rescuer cannot start a thread with the other party. Admin compose reaches the applicant or the reporter and skips `assigned_rescuer_id`. Replies work only after a thread already exists. The rescuer empty state still tells them to file a report or apply to adopt.

Let a resident message the assigned rescuer on their report or case, a rescuer message that resident and an admin, and an admin message the assigned rescuer. Same API, three compose screens (resident web, rescuer web, admin web, and the two phone compose screens).

### 3. Rescuer health record

Website `present`. Phone `unfinished` until a phone is attached. R-FS-4, R-FS-6.

The study puts medical history, vaccines, and heart rate on the admin health page, and that page works. The responder form asks the rescuer to view the animal and to update health and vaccination. A rescuer has `animals.vitals.read` and no screen. Vaccination write is `animals.medical.write`, admin only. Case detail receives `animal_id` and ignores it.

On an assigned case, web and phone: show the animal profile, medical notes, vaccinations, and latest heart rate. Let the rescuer add a vaccination and a heart-rate reading for that animal. Leave animal create/delete, analytics, and verify/assign on the admin site.

### 4. A 3D profile a respondent can open

Website `present` after the animal seed links the two models. Phone `unfinished` until a phone is attached. U-FS-6, objective 9.

The viewers are real: Three.js on the animal page (`public/animals/js/3d-viewer.js`) and `model_viewer_plus` on the phone when `model_3d_url` ends in `.glb` or `.gltf`. Admin upload of `.glb` / `.gltf` / `.obj` and a 360 set is wired. Seed data never sets those fields, so the resident buttons never appear and the phone says “No 3D profile uploaded yet.”

Upload or seed at least one dog and one cat model, then confirm View in 3D on the website and on a phone.

### 5. Play the video that was uploaded

Website player and withdraw-on-failed-upload are in. Phone `unfinished` until a phone is attached. Community requirement “photos or videos.”

Web and phone accept mp4 and webm on a report (up to 8 files, 10 MB). My Reports renders every URL as an image (`public/reports/js/reports.js`). The phone `MediaPhoto` uses `Image.network`, so a video becomes the paw fallback. A report row is kept even when the file upload fails, and the website then shows both an error and a success flash.

Play video on My Reports (web and phone). If the files fail, stay on the form and say the report was not finished.

### 6. Notification links and timing

Website links stay inside the role’s pages. Phone labels and navigation are in the app; the phone row stays `unfinished` until a phone is attached. U-FS-4. `php -S` still cannot hold the live stream.

Inbox, badge, and the events in `NotificationService` exist. On `php -S` the event stream sends one sync and closes (`NotificationController::stream`). The phone polls every 30 seconds and has no push. A resident “view case” link opens `/cases/detail.php`, which the guard allows only for rescuers. Report and adoption links add `?id=` that those pages ignore. Listing notifications use `related_type` `adoption_listing`, and the web client only links `listing`. The phone marks the row read and stays on the list.

Point each notification at a page that role can open. For the evaluation server, run PHP in a mode that holds the stream, or tell respondents the inbox refreshes on the 30-second poll. The built-in one-request server will make “within a reasonable time” hard to agree with.

### 7. Analytics the study names

Website `present` for objective 5 and A-FS-7. The health export reads medical records, not `animal_field_status`.

`/admin/analytics/` has overview counts, completed adoptions by day, a date range, and CSV. It has no rescue-rate or time-to-resolve figure. Geographic distribution is the dashboard heat layer, not a table on this page. The health-updates export reads `animal_field_status`, and no admin screen writes that log.

Add a rescue-activity rate the study can defend (resolved cases against verified reports, or time from assign to resolve) and a short geographic summary next to the existing heatmap. Either add the field-status form or stop exporting a log the UI never writes.

### 8. Records a signed-in user should not read

Website `present` for U-SE-1, R-SE-1, and A-SE-1. A-SE-2 stays `present`.

Login, password hashing, and role gates on admin pages are in place. Any signed-in user can read every animal, including `not_listed`, plus medical notes (`AnimalController`). Rescuers have `reports.read`, so the report index returns every report. Heatmap and per-animal health reads use auth only. The notification stream puts the access token in the query string.

Limit medical reads, the full report index, and the heatmap to the roles that need them. Residents keep their own reports and the public adoption gallery.

### 9. Finish paths that already start

| Gap | Status | Where | What to finish |
|-----|--------|-------|----------------|
| Heatmap includes pending and dismissed pins | present | `fetchHeatmap` now defaults to `verified`. | A-FS-6. |
| Listing cannot go live | present | The listing form collects a vaccination and a weight. Approval can record the same fields. | Objective 7. |
| Animal stays “Available” after an application | present | Apply sets `pending`. Complete sets `adopted`. | U-FS-1. |
| Quiz is seed-only | present | The editor saves `quiz_json`. Residents still receive questions without the answer index. | A-FS-10. |
| Listing card says “an animal” | present | Cards use `animal_name` from the listing API. | |
| Broadcast cannot be removed | present | Remove deletes every copy of that announcement. | A-FS-8. |
| Soft-delete copy promises restore | present | The dialog says the record is hidden. | A-FS-1, A-RE-2. |
| Phone proof is gallery only | unfinished | The case screen offers Camera or Gallery. No phone is attached to try the camera. | R-FS-1 on the phone. |
| Report has no species | present | The report form asks dog or cat. Run `php bin\migrate.php` so `reports.species` exists. | |
| Lesson progress can fail silently | present | A failed save shows a toast on the website. The phone already shows the error. | U-FS-7. |
| Phone notification type is a raw API string | unfinished | The phone shows Report, Adoption, Case, or Listing and opens that record. No phone is attached. | U-US-3, U-FS-4. |

### 10. Study wording that should stay a document, not a rewrite

The requirements line “Reported → Rescued → Under Care → Adopted” is not a status in the database. There is no `under_care`. Reports are `pending_verification` / `verified` / `dismissed`. Cases are `open` / `assigned` / `in_progress` / `resolved`. Adoption is `not_listed` / `available` / `pending` / `adopted`. “Rescued” exists only as a field-status flag.

Keep the working machines. In the paper, map those names onto the four words. Do not rename the schema to match the sentence.

Heart rate is stored (`vitals_log`) and the admin health page can enter it. A device can post with `X-Device-Key`. There is no device screen. Objective 10 is met on the admin website. The rescuer form is item 3 above.

Feedback (“Report a problem”) is in the web shell and the phone account tab. It is not an objective and not a survey statement. Leave it out of the score.

## What a respondent still has to score

These are `rater-only`. Build nothing new for them unless a pass at 375px, 768px, and 1440px, or a phone install, shows a break.

| IDs | What the person does |
|-----|----------------------|
| U-PE-1, U-PE-2, R-PE-1, A-PE-1, A-PE-2, A-PE-3 | Time a report submit, an adoption submit, a case update, an animal save, an analytics CSV, and the map. |
| U-CO-1, U-CO-2, U-PO-1, U-PO-2, R-CO-1, R-CO-2, R-PO-1, A-CO-1, A-CO-2, A-PO-1, A-PO-2 | Website on another browser and at 375 / 768 / 1440. Android app on a physical phone. Wi-Fi and mobile data. There is no offline queue except the phone report outbox. |
| U-US-1 through U-US-3, U-US-5, R-US-1, R-US-2, R-US-5, A-US-1 through A-US-3, A-US-5 | First-time use, labels, and whether the screen looks finished. Landing copy says “Live map of cases” and residents have no case map (`views/home/landing.php`). Admin screens say FurEscue, which is what A-US-1 should say after the AgriVista line is corrected. |
| U-US-4, R-US-4, A-US-4 | Error text exists on the main forms (inline or toast). Silent failures to fix first are in items 5, 6, and 9. |
| U-RE-1, R-RE-1, A-RE-1 | Repeat the main task and see that the session stays up. |
| A-MN-1, A-MN-2 | Tests exist (`php vendor\phpunit\phpunit\phpunit` on SQLite). A rater who wants to see a test run needs that command. New behavior is a route, a controller, and a service. |

## Statement index

User form, resident web and resident phone.

| ID | Web | Phone |
|----|-----|-------|
| U-FS-1 | present — pending while an application is open, adopted when City Vet completes it | present |
| U-FS-2 | present | present |
| U-FS-3 | present | present |
| U-FS-4 | present for links a resident can open; the inbox is a poll, not push (item 6) | unfinished — no phone attached |
| U-FS-5 | present — resident can message the assigned rescuer and an admin (item 2) | unfinished — no phone attached |
| U-FS-6 | present after `php seeders\seed_animals.php` links `/uploads/demo/models/dog.glb` and `cat.glb` (item 4) | unfinished — no phone attached |
| U-FS-7 | present | present |
| U-PE-1, U-PE-2, U-PE-3 | rater-only; U-PE-3 depends on item 6 | rater-only |
| U-CO-1, U-CO-2, U-PO-1 | rater-only (website) | phone app is the mobile client for U-PO-2 |
| U-PO-2 | rater-only at phone width | present as an installed app; ease is rater-only |
| U-US-1, U-US-2, U-US-3, U-US-5 | rater-only | rater-only |
| U-US-4 | rater-only after the silent failures in items 5 and 9 | present |
| U-RE-1 | rater-only | rater-only |
| U-RE-2 | present for reports and adoptions; video playback is item 5 | present; video playback is item 5 |
| U-SE-1 | present — medical notes and other residents’ reports are not readable (item 8) | present for tokens; queued reports sit in an app folder |
| U-SE-2 | present | present |

Responder form. “Mobile web” in R-CO-1 means this Android app.

| ID | Web | Phone |
|----|-----|-------|
| R-FS-1 | present (accept, decline, proof) | unfinished — camera choice is in the case screen; no phone attached |
| R-FS-2 | present — pin on the case page, checked at 375, 768, and 1440 (item 1) | unfinished — no phone attached |
| R-FS-3 | present (accept and decline; City Vet resolves) | present |
| R-FS-4 | present — animal, notes, vaccinations, and heart rate on the case (item 3) | unfinished — no phone attached |
| R-FS-5 | present — rescuer can message that resident and an admin (item 2) | unfinished — no phone attached |
| R-FS-6 | present — vaccination and heart-rate forms on the assigned case (item 3) | unfinished — no phone attached |
| R-PE-1, R-PE-3 | rater-only | rater-only |
| R-PE-2 | rater-only; the pin is on the case page | unfinished — no phone attached |
| R-CO-1, R-CO-2, R-PO-1, R-PO-2 | rater-only | rater-only, after the map exists |
| R-US-1, R-US-2, R-US-5 | rater-only | rater-only |
| R-US-3 | rater-only; the case page has the pin | rater-only; phone unfinished until a device is attached |
| R-US-4 | present | present |
| R-RE-1 | rater-only | rater-only |
| R-RE-2 | present | present |
| R-SE-1 | present — the report index is no longer every report (item 8) | present for the signed-in rescuer’s cases |
| R-SE-2 | present | present |

Administrator form. Website only.

| ID | Web |
|----|-----|
| A-FS-1 | present; restore copy is item 9 |
| A-FS-2, A-FS-3, A-FS-4, A-FS-5, A-FS-9, A-FS-11 | present |
| A-FS-6 | present; the heatmap asks for verified pins (item 9) |
| A-FS-7 | present — rescue rate, hours to resolve, and a place summary (item 7) |
| A-FS-8 | present; Remove calls the existing delete route (item 9) |
| A-FS-10 | present — the lesson editor saves `quiz_json` (item 9) |
| A-FS-12 | present — compose includes the assigned rescuer (item 2) |
| A-PE-1, A-PE-2, A-PE-3, A-CO-1, A-CO-2, A-PO-1, A-PO-2 | rater-only |
| A-US-1 through A-US-3, A-US-5 | rater-only, after the AgriVista line is corrected |
| A-US-4 | present |
| A-RE-1 | rater-only |
| A-RE-2 | present |
| A-SE-1 | present — medical reads, the full report index, and the heatmap are role-gated (item 8) |
| A-SE-2 | present |
| A-MN-1, A-MN-2 | present as a test suite and a route/service layout; a rater still has to see a run |
