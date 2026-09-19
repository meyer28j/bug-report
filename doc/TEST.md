# Test Plan

Manual QA checklist, live doc — grows as new phases land (mirrors [PLAN.md](PLAN.md)'s phase order). Check items off as verified; re-verify anything touched by a later change.

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
