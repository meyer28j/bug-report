# Spec

Master design doc — the canonical source for what this project is and how it's built. Edit sections in place as decisions change; this is current state, not a change log. New design areas get a new header here, not a new file.

## Purpose
A personal log of bugs found on live websites, reported to the company, and (often) fixed via a suggested code patch the author writes themselves. Doubles as a public portfolio piece linked from LinkedIn posts to demonstrate QA skill and attention to detail for job-seeking purposes.

Guiding principle: **keep it as simple as possible.** A lightweight log, not a product. Every field/feature should earn its place — fields get added/removed as real usage reveals what's actually useful.

## Naming
- Public-facing site title: "The Bug Report" (used in `<title>`, header, etc).
- Everything internal — repo name, folders, code, DB file — stays plain `bug-report`, no "bugsite".

## Audience & Tone
- Public-facing site: clean, professional, credible, no jokes/snark in UI copy — read well to recruiters/employers browsing from a LinkedIn link.
- LinkedIn posts (written separately, not part of this build): casual, short blurbs about a new bug or an update, linking to the case page.
- Companies are named openly, no anonymizing.

## Stack
- Existing LAMP server, PHP-FPM.
- SQLite (`cases.db`) — single-writer, low-volume, read-mostly data fits well; avoids DB server overhead.
- No JS framework; plain PHP + minimal CSS.

## Access Model
- Public pages: feed + individual case pages, open to the world.
- Admin pages (`admin/index.php`, `admin/new.php`, `admin/edit.php`): no login/auth — the server is reachable only over a private Tailscale network, so network membership is the security boundary, not app-level auth. No app-level auth on top of this.

## Dev & Deploy
- Build/test locally with PHP's built-in server + the `sqlite3` CLI.
- Web server document root is `src/` (not repo root) — both locally and on the LAMP server.
- Deploy via git: remote configured on the LAMP server, deploy by SSH-ing in and running `git pull`.
- Deploy must `mkdir -p data && mkdir -p src/screenshots` on first setup — both are gitignored so a fresh clone won't have them.

## File Structure
```
/bug-report/
├── doc/                    # this spec + the live plan
├── data/                   # gitignored, outside webroot — private, never HTTP-reachable
│   └── cases.db
└── src/                    # web server document root
    ├── schema.sql          # DB schema (source of truth, run once to create ../data/cases.db)
    ├── index.php           # public feed
    ├── case.php            # public case page (?slug=xxx)
    ├── includes/
    │   ├── db.php          # PDO connection
    │   └── helpers.php     # status badge render, timeline render
    ├── style.css
    ├── admin/
    │   ├── index.php       # list of cases with edit links
    │   ├── new.php         # entry form
    │   └── edit.php        # update an existing case
    └── screenshots/        # gitignored contents, public — flat, slug-prefixed filenames
```

## Slugs
- Auto-generated from `date_reported` + company in `admin/new.php` (e.g. `2026-09-06-acme-corp`), no manual slug field.
- Collisions get a numeric suffix.

## Screenshots
- Three types, uploaded via file inputs in `admin/new.php` and `admin/edit.php`:
  - `screenshot_bug` — required, the original issue.
  - `screenshot_fix_proof` — optional, e.g. a live inspector edit showing the suggested fix works.
  - `screenshot_after` — optional, the real deployed fix once the company ships it.
- Plain file picker (`<input type="file" accept="image/*">`).
- Stored flat in `src/screenshots/`, filenames slug-prefixed (e.g. `2026-09-06-acme-corp-bug.png`).
- Restricted to jpg/png/webp (extension + MIME check), 5MB size cap.

## Suggested Fix Rendering
- Plain `<pre><code>` with CSS monospace styling. No syntax highlighting, no JS.

## Timeline
- `date_reported` and `date_fixed` are recorded and shown as plain milestone dates on the case page timeline: Reported → Fixed.
- `date_fixed` is the date the fix was confirmed/noticed during a check-in, not necessarily the exact date the company shipped it — cases aren't monitored continuously, so there's an inherent margin of error. No company acknowledgment step is tracked; the only input needed from the company's side is whether the issue ends up fixed.

## Security / File Exposure
- `cases.db` lives in `data/`, a sibling of `src/` (the document root) — physically outside webroot, so it's never HTTP-reachable regardless of `.htaccess`. This is the actual guarantee; no rule to get wrong.
- `src/screenshots/` and `src/includes/` are both inside webroot. Screenshots must stay servable (they're shown via `<img>` on public case pages) — only disable directory listing there. `src/includes/` holds only PHP, which the server executes rather than serves as static text, but add a `.htaccess` deny-all anyway as defense in depth.
- Repo tracks structure, not data: `src/schema.sql` is committed; `data/` (the db) and the contents of `src/screenshots/` are gitignored. A fresh checkout gets nothing under either — both are built/populated locally or on the server.

## Feed (`index.php`)
- Chronological list of all cases, sorted newest `date_reported` first. No pagination needed initially.
- One row each: `Date · Company · 1-line issue · Status badge · link`.

## Case Page (`case.php?slug=`)
- Full detail for one case, using the Case Template below — every case page follows the identical section order/format, no free-form variation.
- Unknown slug → plain HTTP 404 page with a minimal "case not found" message and a button back to the home feed (no redirect).
- No narrative/prose sections beyond the plain issue description — no storytelling, no analysis blocks. Personality lives in the LinkedIn post, not the site.

## Case Template (strict, same every time)
In display order:
1. Title — [Company] — [1-line issue]
2. Status badge — Reported / Fixed / No Response / Won't Fix (colors per Visual Design below)
3. Date Reported
4. Company
5. Page URL
6. Reported Via (email/form/social/etc.)
7. Issue description (short)
8. Screenshot — Bug (the original issue)
9. Suggested Fix (code snippet/diff)
10. Screenshot — Fix Proof (optional)
11. Screenshot — After (optional, if applicable)
12. Date Fixed

## Admin
- `admin/index.php` — list of all cases with an edit link per row.
- `admin/new.php` — add a case; required at submit: company, page_url, issue_title, screenshot_bug. Everything else (issue_description, suggested_fix, date_fixed, the other two screenshots) is optional and filled in later via `edit.php`.
- `admin/edit.php?slug=` — loads a case pre-filled, updates it on submit.
- Exact field validation rules: **TBD** — not yet spec'd, keep simple.

## Visual Design
- Responsive — public and admin pages both work at desktop and mobile widths.
- Big, generous whitespace; large easy-to-read type; not cramped.
- Status shown as a small colored badge/pill; screenshots large/clickable, not thumbnails; suggested fix in a monospace block; minimal color palette overall, color used only for status badges; clean sans-serif, dark text on light background.
- Status badge colors — four distinct colors, one per status: Reported=yellow, Fixed=green, No Response=red, Won't Fix=purple.
- Exact hex values, type scale, spacing: **TBD** — fill in here once chosen.

## Out of Scope (for now)
- No analytics dashboard, charts, or leaderboards — just the chronological feed and case pages.
- No multi-user support, no login system.
- No pagination, tagging, search, or filtering — add later only if the feed grows large enough to need it.
- No CMS — direct PHP + SQLite is sufficient.
