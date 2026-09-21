# Plan

Live progress doc. Check items off as completed. Details live in [SPEC.md](SPEC.md), the master design doc — don't duplicate them here.

## Phase 0 — Scaffolding & Environment
- [x] `.gitignore`: `/data/`, `src/screenshots/`
- [x] `LICENSE` (MIT), `README.md`
- [x] Create remaining directory structure per SPEC.md File Structure (`src/index.php`, `src/case.php`, `src/includes/`, `src/style.css`, `src/admin/`, `src/screenshots/`)
- [x] Confirm local PHP + `sqlite3` CLI available
- [ ] Confirm git remote + SSH access to LAMP server for deploy

## Phase 1 — Database
- [x] Write `src/schema.sql`
- [x] Build `data/cases.db` locally from `src/schema.sql` (sibling of `src/`, outside webroot)
- [x] Insert 1–2 seed rows for dev/testing

## Phase 2 — Includes
- [x] `src/includes/db.php` — PDO sqlite connection to `../data/cases.db`, exceptions on error
- [x] `src/includes/helpers.php` — status badge render (colors per SPEC.md)
- [x] `src/includes/helpers.php` — timeline render (Reported → Fixed as plain dates)
- [x] `src/includes/helpers.php` — slug generator (`date_reported` + company, numeric suffix on collision)

## Phase 3 — Public Feed (`src/index.php`)
- [x] Query all cases, newest `date_reported` first
- [x] Render row list: Date · Company · issue_title · status badge · link to case page
- [x] Empty-state (no cases yet)

## Phase 4 — Case Page (`src/case.php`)
- [x] Fetch case by slug (prepared statement); 404 + home button if not found
- [x] Render all 12 template sections in SPEC.md Case Template order
- [x] Render bug/fix-proof/after screenshots, large/clickable (fix-proof and after are optional)
- [x] Render suggested fix in `<pre><code>`
- [x] Render timeline block (Reported → Fixed, plain dates)
- [x] Handle missing optional fields (no fix-proof/after screenshot, not yet fixed)

## Phase 5 — Entry Form (`src/admin/new.php`)
- [x] Fill in SPEC.md's Admin section — exact fields, required vs optional, validation rules
- [x] Build form matching schema fields; only company, page_url, issue_title, screenshot_bug required
- [x] Handle bug screenshot upload (jpg/png/webp, 5MB cap, ext+MIME check; required) — fix-proof/after uploads are edit.php's job (Phase 6)
- [x] Auto-generate slug on submit
- [x] Insert row (prepared statement); redirect to new case page on success
- [x] Show validation errors on failure

## Phase 6 — Edit/Update Case (`src/admin/index.php`, `src/admin/edit.php`)
This is a core, recurring workflow (checking in on cases and updating them), not a one-off — needs to be fast to use.
- [x] `src/admin/index.php` — admin listing of all cases (slug, company, status) with an Edit link per row, so a case is easy to find without knowing its slug
- [x] `src/admin/edit.php?slug=` — load one case, pre-fill form with its current values
- [x] Allow updating status, date_fixed, issue_description, suggested_fix
- [x] Allow uploading/replacing the fix-proof and after screenshots (bug screenshot stays as originally set)
- [x] Update row via prepared UPDATE statement, bump `updated_at`
- [x] Redirect to the case page on success; show validation errors on failure

## Phase 7 — Visual Design
- [x] Fill in SPEC.md's Visual Design section — exact hex values, type scale, spacing
- [x] Build `src/style.css` per SPEC.md Visual Design section
- [x] Use "The Bug Report" as public-facing site title (header, `<title>`)
- [x] Apply across `index.php`, `case.php`, `admin/new.php`, `admin/index.php`, `admin/edit.php`

## Phase 8 — Security Hardening
- [ ] Confirm `data/` sits outside `src/` (document root) — no `.htaccess` needed for `cases.db`, it's structurally unreachable
- [ ] `.htaccess` disable directory listing for `src/screenshots/` (images themselves must stay servable)
- [ ] `.htaccess` deny-all for `src/includes/` (defense in depth)
- [ ] Escape all output (`htmlspecialchars`) — issue_description, suggested_fix, etc.
- [ ] Server-side validation on all form inputs despite the network-level access restriction

## Phase 9 — Deploy
- [ ] Set up Tailscale on the LAMP server and on phone (and any other admin device)
- [ ] Init git remote on LAMP server, initial clone
- [ ] Point vhost document root at `src/`
- [ ] `mkdir -p data && mkdir -p src/screenshots`, build `cases.db` on server from `src/schema.sql`
- [ ] Confirm `data/` and `src/screenshots/` writable by PHP-FPM
- [ ] First deploy, smoke test in production

## Phase 10 — Launch
- [ ] Add first real case via admin form
- [ ] Verify feed + case page in production
- [ ] LinkedIn post (external, author-written)

## Deferred ideas
- Paste-from-clipboard for screenshot uploads (`new.php`'s `screenshot_bug`, later `edit.php`'s fix-proof/after) — small vanilla-JS enhancement via `DataTransfer`, held until after Phase 5/6 are tested.
- Self-referential 404 easter egg: when `case.php`'s 404 is hit, auto-create a case logging the site's own bug, then show it to the visitor with a link to the case and a link home. Needs a dedupe key (e.g. the requested slug/path) so repeat visits to the same broken link reuse the existing case instead of creating duplicates.
