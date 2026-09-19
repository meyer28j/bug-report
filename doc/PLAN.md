# Plan

Live progress doc. Check items off as completed. Details live in [SPEC.md](SPEC.md), the master design doc — don't duplicate them here.

## Phase 0 — Scaffolding & Environment
- [x] `.gitignore`: `/data/`, `src/screenshots/*` (keep dir via `.gitkeep`)
- [x] `LICENSE` (MIT), `README.md`
- [ ] Create remaining directory structure per SPEC.md File Structure (`src/index.php`, `src/case.php`, `src/includes/`, `src/style.css`, `src/admin/`, `src/screenshots/.gitkeep`)
- [ ] Confirm local PHP + `sqlite3` CLI available
- [ ] Confirm git remote + SSH access to LAMP server for deploy

## Phase 1 — Database
- [x] Write `src/schema.sql`
- [ ] Build `data/cases.db` locally from `src/schema.sql` (sibling of `src/`, outside webroot)
- [ ] Insert 1–2 seed rows for dev/testing

## Phase 2 — Includes
- [ ] `src/includes/db.php` — PDO sqlite connection to `../data/cases.db`, exceptions on error
- [ ] `src/includes/helpers.php` — status badge render (colors per SPEC.md)
- [ ] `src/includes/helpers.php` — timeline render (Reported → Acknowledged → Fixed as plain dates)
- [ ] `src/includes/helpers.php` — slug generator (`date_reported` + company, numeric suffix on collision)

## Phase 3 — Public Feed (`src/index.php`)
- [ ] Query all cases, newest `date_reported` first
- [ ] Render row list: Date · Company · issue_title · status badge · link to case page
- [ ] Empty-state (no cases yet)

## Phase 4 — Case Page (`src/case.php`)
- [ ] Fetch case by slug (prepared statement); 404 + home button if not found
- [ ] Render all 13 template sections in SPEC.md Case Template order
- [ ] Render bug/fix-proof/after screenshots, large/clickable (fix-proof and after are optional)
- [ ] Render suggested fix in `<pre><code>`
- [ ] Render timeline block (Reported → Acknowledged → Fixed, plain dates)
- [ ] Handle missing optional fields (no fix-proof/after screenshot, not yet acknowledged/fixed)

## Phase 5 — Entry Form (`src/admin/new.php`)
- [ ] Fill in SPEC.md's Admin section — exact fields, required vs optional, validation rules
- [ ] Build form matching schema fields; only company, page_url, issue_title, screenshot_bug required
- [ ] Handle bug/fix-proof/after file uploads (jpg/png/webp, 5MB cap, ext+MIME check; bug required, others optional)
- [ ] Auto-generate slug on submit
- [ ] Insert row (prepared statement); redirect to new case page on success
- [ ] Show validation errors on failure

## Phase 6 — Edit/Update Case (`src/admin/index.php`, `src/admin/edit.php`)
This is a core, recurring workflow (checking in on cases and updating them), not a one-off — needs to be fast to use.
- [ ] `src/admin/index.php` — admin listing of all cases (slug, company, status) with an Edit link per row, so a case is easy to find without knowing its slug
- [ ] `src/admin/edit.php?slug=` — load one case, pre-fill form with its current values
- [ ] Allow updating status, date_acknowledged, date_fixed, issue_description, suggested_fix
- [ ] Allow uploading/replacing the fix-proof and after screenshots (bug screenshot stays as originally set)
- [ ] Update row via prepared UPDATE statement, bump `updated_at`
- [ ] Redirect to the case page on success; show validation errors on failure

## Phase 7 — Visual Design
- [ ] Fill in SPEC.md's Visual Design section — exact hex values, type scale, spacing
- [ ] Build `src/style.css` per SPEC.md Visual Design section
- [ ] Use "The Bug Report" as public-facing site title (header, `<title>`)
- [ ] Apply across `index.php`, `case.php`, `admin/new.php`, `admin/index.php`, `admin/edit.php`

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
