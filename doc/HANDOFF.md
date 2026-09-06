# The Bug Report — Dev Handoff

**Title: "The Bug Report"** (public-facing site name). Internal references — repo, folders, code, DB file — all use `bug-report` / `bugsite`-free naming; see File Structure below.

## Purpose
A personal log of bugs found on live websites, reported to the company, and (often) fixed via a suggested code patch the author writes themselves. Doubles as a public portfolio piece linked from LinkedIn posts to demonstrate QA skill and attention to detail for job-seeking purposes.

**Guiding principle: keep it as simple as possible.** This is a lightweight log, not a product. Every field/feature should earn its place. Author will add/remove fields as real usage reveals what's actually useful — don't over-engineer.

## Audience & Tone
- Public-facing site: clean, professional, credible. No jokes/snark in UI copy — this needs to read well to recruiters/employers browsing from a LinkedIn link.
- LinkedIn posts (written separately by author, not part of this build): casual, short blurbs about a new bug or an update to an ongoing case, linking to the case page.
- Companies are named openly (no anonymizing).

## Stack
- Existing LAMP server, PHP-FPM
- SQLite (`cases.db`) — single-writer, low-volume, read-mostly data fits SQLite well; avoids DB server overhead
- No JS framework needed; plain PHP + minimal CSS

## Access Model
- Public pages: feed + individual case pages, open to the world.
- Entry/admin form: plain PHP page, **no login/auth needed** — server is only reachable via SSH on a local network, so network access is already the security boundary. Do not add app-level auth on top of this.

## Site Structure
- **Feed (`index.php`)**: chronological list of all cases. One row each: `Date · Company · 1-line issue · Status badge · link`. No pagination needed initially.
- **Case page (`case.php?slug=`)**: full detail for one case, using the strict template below. Every case page follows the identical section order/format — no free-form variation.
- **Entry form (`admin/new.php`)**: local-network-only page to add a new case. Simple form matching the schema fields. (No further UX spec yet — build simple, iterate.)

## Case Template (strict, same every time)
In display order:
1. Title — [Company] — [1-line issue]
2. Status badge — Reported / Acknowledged / Fixed / No Response / Won't Fix (color-coded: gray/yellow/green/red)
3. Date Found
4. Date Reported
5. Company
6. Page URL
7. Reported Via (email/form/social/etc.)
8. Issue description (short)
9. Screenshot — Before
10. Suggested Fix (code snippet/diff)
11. Screenshot — After (if applicable)
12. Date Acknowledged
13. Date Fixed
14. Response Time (calculated, not stored)
15. Resolution Time (calculated, not stored)

No narrative/prose sections beyond the plain issue description — no storytelling, no analysis blocks. Personality lives in the LinkedIn post, not the site.

## Visual Design
- Big, generous whitespace; large easy-to-read type; not cramped
- Status shown as a small colored badge/pill
- Screenshots displayed large/clickable, not tiny thumbnails
- Suggested fix shown in a monospace/syntax-highlighted code block
- Small timeline stat block near the bottom of each case page (Reported → Acknowledged → Fixed, with day counts) — this is the "proof of follow-through" element
- Minimal color palette overall; color used only for status badges
- Clean sans-serif, dark text on light background

## Database Schema

```sql
CREATE TABLE cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,          -- e.g. "2026-09-06-acme-corp"
    company TEXT NOT NULL,
    page_url TEXT NOT NULL,
    issue_title TEXT NOT NULL,          -- 1-line summary
    issue_description TEXT,
    status TEXT NOT NULL DEFAULT 'reported', -- reported/acknowledged/fixed/no_response/wont_fix
    reported_via TEXT,                  -- email/form/twitter/etc

    date_found TEXT,                    -- ISO 8601 dates stored as TEXT
    date_reported TEXT NOT NULL,
    date_acknowledged TEXT,
    date_fixed TEXT,

    screenshot_before TEXT,             -- file path
    screenshot_after TEXT,
    suggested_fix TEXT,                 -- raw code snippet/diff text

    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

Response time / resolution time are **calculated in PHP from the date fields**, not stored as columns (avoids stale derived data).

## File Structure

```
/bug-report/
├── cases.db
├── index.php              # public feed
├── case.php               # public case page (?slug=xxx)
├── includes/
│   ├── db.php             # PDO connection
│   └── helpers.php        # date-diff calc, status badge render
├── screenshots/
│   ├── before/
│   └── after/
├── style.css
└── admin/
    └── new.php            # entry form, local-network only, no auth
```

## Explicitly Out of Scope (for now)
- No analytics dashboard, charts, or leaderboards — just the chronological feed and case pages
- No multi-user support, no login system
- No pagination, tagging, search, or filtering — add later only if the feed grows large enough to need it
- No CMS — direct PHP + SQLite is sufficient

## Open / Not Yet Decided
- Exact fields and validation for the entry form (`admin/new.php`) — not yet spec'd, keep simple
- Whether an edit form is needed later for updating status/dates on existing cases, or whether direct DB edits suffice for now
