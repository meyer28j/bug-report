# Test Plan

Manual QA checklist, live doc — grows as new phases land (mirrors [PLAN.md](PLAN.md)'s phase order). Check items off as verified; re-verify anything touched by a later change.

`./test.sh` automates most of the functional checks below (status codes, validation messages, DB state, file uploads, escaping) against a scratch copy of the DB — run it before working through this list by hand. What's left here is what a script can't judge: layout, color, responsiveness, and anything that needs eyes on a real browser.

## Setup
- `./run.sh` — PHP built-in server on `127.0.0.1:8000`, document root `src/`.
- `data/cases.db` built from `src/schema.sql`; `src/screenshots/` exists. Both are gitignored — create locally if missing.

## Public Feed (`index.php`)
- [ ] No cases in DB → empty-state message shown, no errors
- [ ] Cases present → listed newest `date_reported` first
- [ ] Each row shows date, company, issue title (linked), status badge
- [ ] Status badge label matches case status (Reported / Fixed / No Response / Won't Fix)
- [ ] Case title link goes to the correct `case.php?slug=`

## Case Page (`case.php`)
- [ ] Unknown/missing slug → HTTP 404, "case not found" message, home button works
- [ ] All required fields render: title, status badge, date reported, company, page URL (linked), bug screenshot
- [ ] Optional fields absent (`reported_via`, `issue_description`, `suggested_fix`, fix-proof/after screenshots, `date_fixed`) → section hidden, no PHP warnings or empty markup
- [ ] Optional fields present → all render, in template order
- [ ] Screenshot images are clickable, link to the full-size file
- [ ] Timeline always shows Reported date; Fixed date only when set (em dash otherwise)
- [ ] Suggested fix renders as plain preformatted text (monospace, no code execution)

## Entry Form (`admin/new.php`)
- [ ] GET request shows an empty form, `date_reported` pre-filled with today
- [ ] Valid submission (all required fields + valid bug screenshot) → row inserted, redirects to the new case page, case page shows correct data
- [ ] Missing company/page_url/issue_title/screenshot_bug → inline error per missing field, entered values preserved (file input can't be)
- [ ] Invalid `page_url` (not http/https, malformed) → inline error, blocks submit
- [ ] `company`/`issue_title` over 200 chars → inline error
- [ ] `reported_via` over 100 chars → inline error
- [ ] Invalid `date_reported` format → inline error
- [ ] `screenshot_bug` wrong file type (e.g. `.gif`, `.pdf`) → inline error, no file saved
- [ ] `screenshot_bug` over 5MB → inline error, no file saved
- [ ] Valid jpg/png/webp screenshot → saved to `src/screenshots/` with slug-prefixed filename, correct extension
- [ ] Two cases with the same `date_reported` + company → second gets a numeric-suffixed slug, no collision/overwrite
- [ ] Optional fields (`issue_description`, `suggested_fix`, `reported_via`) left blank → stored as `NULL`, case page hides those sections

## Admin Listing (`admin/index.php`)
- [ ] No cases in DB → empty-state message shown, no errors
- [ ] Cases present → each row shows company + status badge, with a working Edit link per row
- [ ] Edit link goes to the correct `edit.php?slug=`

## Edit Case (`admin/edit.php`)
- [ ] Unknown/missing slug → HTTP 404, "case not found" message, back-to-admin link works
- [ ] GET on a known slug shows read-only context (company, issue title, page URL, date reported, reported via) and a form pre-filled with the case's current status, date fixed, issue description, and suggested fix
- [ ] Company, page URL, issue title, date reported, and reported via are never editable from this form (no inputs for them)
- [ ] Changing status + saving → case page badge updates immediately
- [ ] Setting date_fixed + saving → case page timeline shows the new Fixed date
- [ ] Invalid status or malformed date_fixed → inline error, no partial DB update
- [ ] Uploading a fix-proof or after screenshot replaces only that field; the bug screenshot and the other optional screenshot are untouched
- [ ] Leaving a screenshot file input blank on submit keeps the existing screenshot (doesn't clear it)
- [ ] Re-editing a case a second time still redirects correctly and doesn't duplicate the row (same `id`/`slug`)

## Manual/Visual — General
These need eyes on a real browser; `./test.sh` can't judge them.
- [ ] Every page (feed, case, 404, admin index, new, edit) loads with no PHP warnings/notices visible in the HTML source or in `./run.sh`'s terminal output
- [ ] Bug/fix-proof/after screenshot thumbnails are clickable and open the full-size original image in a new context (not cropped/scaled unexpectedly)
- [ ] Page URL links on the case page open the actual reported page in a new context, not a relative/broken link
- [ ] Resize the browser to a phone width on the feed, case page, and both admin forms — content stays usable (no horizontal scroll, buttons/inputs stay tappable). Note: full responsive polish is Phase 7 work; this check is just "not broken," not "styled"
- [ ] Tab through the entry and edit forms with the keyboard — focus order is logical, nothing is unreachable
- [ ] Submit the edit form with a large (but under 5MB) real photo, not a synthetic test file, to sanity-check real-world upload behavior
- [ ] On a phone or via the OS file picker, confirm the file inputs actually let you pick jpg/png/webp files (accept attribute behaves as expected)
