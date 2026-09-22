#!/bin/bash
# Automated smoke test for bug-report, covering Phases 1-6 and the
# app-level parts of Phase 8 of doc/PLAN.md (the two .htaccess files need a
# real Apache server — see doc/TEST.md).
# Runs against a scratch copy of the DB/screenshots on a separate port so it
# never touches real dev data. Manual/visual checks that can't be automated
# (layout, colors, responsiveness, etc.) live in doc/TEST.md.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
SRC="$ROOT/src"
DATA_DIR="$ROOT/data"
DATA_DB="$DATA_DIR/cases.db"
SCREENSHOTS_DIR="$SRC/screenshots"
PORT=8099
BASE="http://127.0.0.1:$PORT"

PASS=0
FAIL=0
FAILURES=()

# ---------------------------------------------------------------------------
# Setup: back up any real dev data, build a fresh scratch DB + screenshots
# dir, start an isolated PHP server, restore everything on exit.
# ---------------------------------------------------------------------------

BACKUP_DIR=$(mktemp -d)
TMP_UPLOADS=$(mktemp -d)
SERVER_PID=""

cleanup() {
    if [ -n "$SERVER_PID" ]; then
        kill "$SERVER_PID" 2>/dev/null
        wait "$SERVER_PID" 2>/dev/null
    fi

    rm -f "$DATA_DB"
    mkdir -p "$DATA_DIR"
    if [ -f "$BACKUP_DIR/cases.db.bak" ]; then
        cp "$BACKUP_DIR/cases.db.bak" "$DATA_DB"
    fi

    rm -rf "$SCREENSHOTS_DIR"
    mkdir -p "$SCREENSHOTS_DIR"
    if [ -d "$BACKUP_DIR/screenshots.bak" ]; then
        cp -a "$BACKUP_DIR/screenshots.bak/." "$SCREENSHOTS_DIR/" 2>/dev/null
    fi

    rm -rf "$BACKUP_DIR" "$TMP_UPLOADS"
}
trap cleanup EXIT

[ -f "$DATA_DB" ] && cp "$DATA_DB" "$BACKUP_DIR/cases.db.bak"
[ -d "$SCREENSHOTS_DIR" ] && cp -a "$SCREENSHOTS_DIR" "$BACKUP_DIR/screenshots.bak"

mkdir -p "$DATA_DIR"
rm -f "$DATA_DB"
sqlite3 "$DATA_DB" < "$SRC/schema.sql"

rm -rf "$SCREENSHOTS_DIR"
mkdir -p "$SCREENSHOTS_DIR"

# Minimal valid 1x1 PNG, well below any size cap.
echo "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=" \
    | base64 -d > "$TMP_UPLOADS/valid.png"
# Minimal valid 1x1 JPEG, for jpg/jpeg-specific extension handling.
echo "/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=" \
    | base64 -d > "$TMP_UPLOADS/valid.jpg"
# Not an image at all, wrong extension too.
printf 'not an image' > "$TMP_UPLOADS/bad.pdf"
# Non-image bytes disguised with a valid image extension + Content-Type header
# — the actual attack server-side MIME sniffing (mime_content_type()) defends against.
printf 'not an image' > "$TMP_UPLOADS/fake.png"
# Over the app's 5MB cap.
head -c 6291456 /dev/urandom > "$TMP_UPLOADS/big.png"

# Raise ini limits above the app's 5MB cap so the app's own validation is
# what gets tested, not PHP's default 2M upload_max_filesize.
php -d upload_max_filesize=10M -d post_max_size=11M -S "127.0.0.1:$PORT" -t "$SRC" \
    >"$TMP_UPLOADS/server.log" 2>&1 &
SERVER_PID=$!

ready=0
for _ in $(seq 1 30); do
    if curl -s -o /dev/null "$BASE/index.php"; then
        ready=1
        break
    fi
    sleep 0.2
done
if [ "$ready" -ne 1 ]; then
    echo "Server never came up; log:"
    cat "$TMP_UPLOADS/server.log"
    exit 1
fi

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

section() { echo; echo "== $1 =="; }
pass() { PASS=$((PASS + 1)); echo "  PASS: $1"; }
fail() { FAIL=$((FAIL + 1)); FAILURES+=("$1"); echo "  FAIL: $1"; }

assert_contains() {
    if echo "$1" | grep -qF -- "$2"; then pass "$3"; else fail "$3 (expected to find: $2)"; fi
}
assert_not_contains() {
    if echo "$1" | grep -qF -- "$2"; then fail "$3 (did not expect to find: $2)"; else pass "$3"; fi
}
assert_eq() {
    if [ "$1" = "$2" ]; then pass "$3"; else fail "$3 (expected '$2', got '$1')"; fi
}

# GET/POST a path, print raw response (headers + body) to stdout.
req() {
    local method="$1" path="$2"
    shift 2
    curl -s -i -X "$method" "$BASE$path" "$@"
}

status_of() { echo "$1" | head -1 | awk '{print $2}'; }
location_of() { echo "$1" | grep -i '^Location:' | head -1 | sed -E 's/^[Ll]ocation: *//' | tr -d '\r\n'; }

sql() { sqlite3 "$DATA_DB" "$1"; }
sql_null() { sqlite3 -cmd '.nullvalue __NULL__' "$DATA_DB" "$1"; }

# ---------------------------------------------------------------------------
# Phase 0 — Scaffolding
# ---------------------------------------------------------------------------
section "Phase 0 — Scaffolding"
for f in index.php case.php schema.sql includes/db.php includes/helpers.php \
         admin/new.php admin/edit.php admin/index.php; do
    if [ -f "$SRC/$f" ]; then pass "src/$f exists"; else fail "src/$f missing"; fi
done

# ---------------------------------------------------------------------------
# Phase 3 — Public Feed (empty state)
# ---------------------------------------------------------------------------
section "Phase 3 — Public Feed (empty)"
resp=$(req GET /index.php)
assert_eq "$(status_of "$resp")" "200" "GET /index.php returns 200 with no cases"
assert_contains "$resp" "No cases yet." "empty feed shows empty-state message"

# ---------------------------------------------------------------------------
# Phase 6 — Admin listing (empty state, before any cases exist)
# ---------------------------------------------------------------------------
section "Phase 6 — Admin Listing (empty)"
resp=$(req GET /admin/index.php)
assert_eq "$(status_of "$resp")" "200" "GET /admin/index.php returns 200 with no cases"
assert_contains "$resp" "No cases yet." "empty admin listing shows empty-state message"

# ---------------------------------------------------------------------------
# Phase 4 — Case page 404
# ---------------------------------------------------------------------------
section "Phase 4 — Case Page (404)"
resp=$(req GET "/case.php?slug=does-not-exist")
assert_eq "$(status_of "$resp")" "404" "unknown slug returns HTTP 404"
assert_contains "$resp" "Case not found." "404 page shows case-not-found message"
assert_contains "$resp" 'href="index.php"' "404 page links back home"

# ---------------------------------------------------------------------------
# Phase 5 — Entry form (admin/new.php)
# ---------------------------------------------------------------------------
section "Phase 5 — Entry Form"

resp=$(req GET /admin/new.php)
assert_eq "$(status_of "$resp")" "200" "GET /admin/new.php returns 200"
assert_contains "$resp" "value=\"$(date +%Y-%m-%d)\"" "date_reported pre-filled with today"

count_before=$(sql "SELECT COUNT(*) FROM cases;")
resp=$(req POST /admin/new.php \
    --form-string "company=Zed Co" \
    --form-string "page_url=https://zed.example.com/pricing" \
    --form-string "issue_title=Broken pricing table" \
    --form-string "issue_description=Table overflows on mobile." \
    --form-string "reported_via=email" \
    --form-string "date_reported=2026-06-01" \
    --form-string "suggested_fix=.pricing { overflow-x: auto; }" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
loc=$(location_of "$resp")
assert_eq "$(status_of "$resp")" "302" "valid submission redirects (302)"
assert_contains "$loc" "case.php?slug=2026-06-01-zed-co" "redirect points at new case's slug"
count_after=$(sql "SELECT COUNT(*) FROM cases;")
assert_eq "$count_after" "$((count_before + 1))" "valid submission inserts exactly one row"
if [ -f "$SCREENSHOTS_DIR/2026-06-01-zed-co-bug.png" ]; then
    pass "bug screenshot saved with slug-prefixed filename"
else
    fail "bug screenshot saved with slug-prefixed filename"
fi

# Missing required fields
count_before=$(sql "SELECT COUNT(*) FROM cases;")
resp=$(req POST /admin/new.php --form-string "issue_description=x")
assert_eq "$(status_of "$resp")" "200" "missing required fields re-renders form (200)"
assert_contains "$resp" "Company is required." "missing company shows inline error"
assert_contains "$resp" "Page URL is required." "missing page_url shows inline error"
assert_contains "$resp" "Issue title is required." "missing issue_title shows inline error"
assert_contains "$resp" "Bug screenshot is required." "missing screenshot shows inline error"
count_after=$(sql "SELECT COUNT(*) FROM cases;")
assert_eq "$count_after" "$count_before" "no row inserted on missing-field failure"

# Invalid page_url
resp=$(req POST /admin/new.php \
    --form-string "company=A" --form-string "page_url=not-a-url" \
    --form-string "issue_title=T" --form-string "date_reported=2026-06-02" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
assert_contains "$resp" "Enter a valid http(s) URL." "invalid page_url shows inline error"
assert_contains "$resp" 'value="A"' "valid fields are preserved in the re-rendered form on validation failure"

# Over-length fields
long201=$(printf 'a%.0s' $(seq 1 201))
resp=$(req POST /admin/new.php \
    --form-string "company=$long201" --form-string "page_url=https://x.example.com" \
    --form-string "issue_title=T" --form-string "date_reported=2026-06-02" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
assert_contains "$resp" "Company must be 200 characters or fewer." "over-length company shows inline error"

long101=$(printf 'a%.0s' $(seq 1 101))
resp=$(req POST /admin/new.php \
    --form-string "company=A" --form-string "page_url=https://x.example.com" \
    --form-string "issue_title=T" --form-string "date_reported=2026-06-02" \
    --form-string "reported_via=$long101" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
assert_contains "$resp" "Reported via must be 100 characters or fewer." "over-length reported_via shows inline error"

# Invalid date
resp=$(req POST /admin/new.php \
    --form-string "company=A" --form-string "page_url=https://x.example.com" \
    --form-string "issue_title=T" --form-string "date_reported=not-a-date" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
assert_contains "$resp" "Enter a valid date (YYYY-MM-DD)." "invalid date_reported shows inline error"

# Wrong file type
count_before=$(sql "SELECT COUNT(*) FROM cases;")
files_before=$(ls "$SCREENSHOTS_DIR" | wc -l)
resp=$(req POST /admin/new.php \
    --form-string "company=A" --form-string "page_url=https://x.example.com" \
    --form-string "issue_title=T" --form-string "date_reported=2026-06-03" \
    -F "screenshot_bug=@$TMP_UPLOADS/bad.pdf;type=application/pdf")
assert_contains "$resp" "File must be a JPG, PNG, or WEBP image." "wrong file type shows inline error"
count_after=$(sql "SELECT COUNT(*) FROM cases;")
files_after=$(ls "$SCREENSHOTS_DIR" | wc -l)
assert_eq "$count_after" "$count_before" "no row inserted for wrong file type"
assert_eq "$files_after" "$files_before" "no file saved for wrong file type"

# Spoofed MIME: valid-looking extension + Content-Type header, non-image bytes
count_before=$(sql "SELECT COUNT(*) FROM cases;")
files_before=$(ls "$SCREENSHOTS_DIR" | wc -l)
resp=$(req POST /admin/new.php \
    --form-string "company=A" --form-string "page_url=https://x.example.com" \
    --form-string "issue_title=T" --form-string "date_reported=2026-06-03" \
    -F "screenshot_bug=@$TMP_UPLOADS/fake.png;type=image/png")
assert_contains "$resp" "File must be a JPG, PNG, or WEBP image." "spoofed MIME (image extension/header, non-image bytes) is rejected"
count_after=$(sql "SELECT COUNT(*) FROM cases;")
files_after=$(ls "$SCREENSHOTS_DIR" | wc -l)
assert_eq "$count_after" "$count_before" "no row inserted for spoofed MIME upload"
assert_eq "$files_after" "$files_before" "no file saved for spoofed MIME upload"

# Oversized file
resp=$(req POST /admin/new.php \
    --form-string "company=A" --form-string "page_url=https://x.example.com" \
    --form-string "issue_title=T" --form-string "date_reported=2026-06-04" \
    -F "screenshot_bug=@$TMP_UPLOADS/big.png;type=image/png")
assert_contains "$resp" "File exceeds the 5MB size limit." "oversized file shows inline error"

# Slug collision (same date_reported + company as the first submission)
resp=$(req POST /admin/new.php \
    --form-string "company=Zed Co" --form-string "page_url=https://zed.example.com/other" \
    --form-string "issue_title=Second issue" --form-string "date_reported=2026-06-01" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
loc=$(location_of "$resp")
assert_contains "$loc" "slug=2026-06-01-zed-co-2" "colliding slug gets numeric suffix"

# JPG upload (jpg-specific extension logic: both .jpg and .jpeg are valid)
resp=$(req POST /admin/new.php \
    --form-string "company=Jpg Co" --form-string "page_url=https://jpg.example.com" \
    --form-string "issue_title=Jpg case" --form-string "date_reported=2026-06-07" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.jpg;type=image/jpeg")
if [ -f "$SCREENSHOTS_DIR/2026-06-07-jpg-co-bug.jpg" ]; then
    pass "valid jpg upload saved with .jpg extension"
else
    fail "valid jpg upload saved with .jpg extension"
fi

# Optional fields left blank -> stored as NULL
resp=$(req POST /admin/new.php \
    --form-string "company=Blank Co" --form-string "page_url=https://blank.example.com" \
    --form-string "issue_title=Minimal case" --form-string "date_reported=2026-06-05" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
desc=$(sql_null "SELECT issue_description FROM cases WHERE slug='2026-06-05-blank-co';")
assert_eq "$desc" "__NULL__" "blank issue_description stored as NULL"
fix=$(sql_null "SELECT suggested_fix FROM cases WHERE slug='2026-06-05-blank-co';")
assert_eq "$fix" "__NULL__" "blank suggested_fix stored as NULL"
via=$(sql_null "SELECT reported_via FROM cases WHERE slug='2026-06-05-blank-co';")
assert_eq "$via" "__NULL__" "blank reported_via stored as NULL"

# ---------------------------------------------------------------------------
# Phase 4 (cont.) — Case Page rendering
# ---------------------------------------------------------------------------
section "Phase 4 — Case Page (rendering)"

resp=$(req GET "/case.php?slug=2026-06-01-zed-co")
assert_eq "$(status_of "$resp")" "200" "known slug returns 200"
assert_contains "$resp" "Zed Co" "case page shows company"
assert_contains "$resp" "Broken pricing table" "case page shows issue title"
assert_contains "$resp" "badge-reported" "case page shows Reported status badge"
assert_contains "$resp" "Reported Via" "case page shows optional reported_via section when present"
assert_contains "$resp" "Suggested Fix" "case page shows suggested fix section when present"
assert_contains "$resp" "screenshots/2026-06-01-zed-co-bug.png" "case page links bug screenshot"

resp=$(req GET "/case.php?slug=2026-06-05-blank-co")
assert_not_contains "$resp" "Reported Via" "case page hides reported_via when absent"
assert_not_contains "$resp" "Suggested Fix" "case page hides suggested fix when absent"
assert_not_contains "$resp" "issue-description" "case page hides issue description when absent"
assert_contains "$resp" ">—<" "timeline shows em dash for unset date_fixed"

# XSS / output escaping spot check (ahead of Phase 8, but cheap to guard now)
resp=$(req POST /admin/new.php \
    --form-string "company=<script>alert(1)</script>" --form-string "page_url=https://xss.example.com" \
    --form-string "issue_title=XSS check" --form-string "date_reported=2026-06-06" \
    --form-string "issue_description=<img src=x onerror=alert(1)>" \
    -F "screenshot_bug=@$TMP_UPLOADS/valid.png;type=image/png")
loc=$(location_of "$resp")
slug=$(echo "$loc" | sed -E 's/.*slug=//')
resp=$(req GET "/case.php?slug=$slug")
assert_contains "$resp" "&lt;script&gt;alert(1)&lt;/script&gt;" "company with HTML is escaped on case page"
assert_not_contains "$resp" "<script>alert(1)</script>" "raw script tag never appears unescaped"
assert_contains "$resp" "&lt;img src=x onerror=alert(1)&gt;" "issue_description with HTML is escaped"

# ---------------------------------------------------------------------------
# Phase 3 (cont.) — Public Feed with cases present
# ---------------------------------------------------------------------------
section "Phase 3 — Public Feed (populated)"

resp=$(req GET /index.php)
assert_eq "$(status_of "$resp")" "200" "GET /index.php returns 200 with cases"
pos_blank=$(echo "$resp" | grep -n "2026-06-05" | head -1 | cut -d: -f1)
pos_zed=$(echo "$resp" | grep -n "2026-06-01" | head -1 | cut -d: -f1)
if [ -n "$pos_blank" ] && [ -n "$pos_zed" ] && [ "$pos_blank" -lt "$pos_zed" ]; then
    pass "feed lists newest date_reported first"
else
    fail "feed lists newest date_reported first (blank@$pos_blank, zed@$pos_zed)"
fi
assert_contains "$resp" 'case.php?slug=2026-06-01-zed-co' "feed links each case to its slug"

# ---------------------------------------------------------------------------
# Phase 6 — Admin listing (admin/index.php)
# ---------------------------------------------------------------------------
section "Phase 6 — Admin Listing"

resp=$(req GET /admin/index.php)
assert_eq "$(status_of "$resp")" "200" "GET /admin/index.php returns 200"
assert_contains "$resp" "Zed Co" "admin listing shows case company"
assert_contains "$resp" 'edit.php?slug=2026-06-01-zed-co' "admin listing links to edit.php per case"
assert_contains "$resp" "badge-reported" "admin listing shows status badge"

# ---------------------------------------------------------------------------
# Phase 6 — Edit case (admin/edit.php)
# ---------------------------------------------------------------------------
section "Phase 6 — Edit Case"

resp=$(req GET "/admin/edit.php?slug=does-not-exist")
assert_eq "$(status_of "$resp")" "404" "edit.php on unknown slug returns 404"
assert_contains "$resp" "Case not found." "edit.php 404 shows case-not-found message"

resp=$(req GET "/admin/edit.php?slug=2026-06-01-zed-co")
assert_eq "$(status_of "$resp")" "200" "GET edit.php on known slug returns 200"
assert_contains "$resp" "Zed Co" "edit form shows read-only company for context"
assert_contains "$resp" "Broken pricing table" "edit form shows read-only issue title for context"
assert_contains "$resp" '<option value="reported" selected>' "edit form pre-selects current status"
assert_contains "$resp" "Table overflows on mobile." "edit form pre-fills issue_description"

# Valid update: status, date_fixed, description, suggested_fix, fix-proof screenshot
resp=$(req POST "/admin/edit.php?slug=2026-06-01-zed-co" \
    --form-string "status=fixed" \
    --form-string "date_fixed=2026-06-10" \
    --form-string "issue_description=Fixed via overflow-x." \
    --form-string "suggested_fix=.pricing { overflow-x: auto; } /* v2 */" \
    -F "screenshot_fix_proof=@$TMP_UPLOADS/valid.png;type=image/png")
loc=$(location_of "$resp")
assert_eq "$(status_of "$resp")" "302" "valid edit redirects (302)"
assert_contains "$loc" "case.php?slug=2026-06-01-zed-co" "edit redirects to the case page"
newstatus=$(sql "SELECT status FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$newstatus" "fixed" "status updated in DB"
newfixed=$(sql "SELECT date_fixed FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$newfixed" "2026-06-10" "date_fixed updated in DB"
if [ -f "$SCREENSHOTS_DIR/2026-06-01-zed-co-fix-proof.png" ]; then
    pass "fix-proof screenshot saved with slug-prefixed filename"
else
    fail "fix-proof screenshot saved with slug-prefixed filename"
fi
unchanged_company=$(sql "SELECT company FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$unchanged_company" "Zed Co" "bug screenshot's original company untouched by edit"
bugshot=$(sql "SELECT screenshot_bug FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$bugshot" "2026-06-01-zed-co-bug.png" "original bug screenshot untouched by edit"

# Case page reflects the update
resp=$(req GET "/case.php?slug=2026-06-01-zed-co")
assert_contains "$resp" "badge-fixed" "case page reflects updated status badge"
assert_contains "$resp" "2026-06-10" "case page reflects updated date_fixed in timeline"
assert_contains "$resp" "screenshots/2026-06-01-zed-co-fix-proof.png" "case page shows new fix-proof screenshot"

# Invalid status
resp=$(req POST "/admin/edit.php?slug=2026-06-01-zed-co" --form-string "status=bogus")
assert_contains "$resp" "Select a valid status." "invalid status shows inline error"
stillfixed=$(sql "SELECT status FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$stillfixed" "fixed" "invalid status submission leaves DB row unchanged"

# Invalid date_fixed
resp=$(req POST "/admin/edit.php?slug=2026-06-01-zed-co" \
    --form-string "status=fixed" --form-string "date_fixed=not-a-date")
assert_contains "$resp" "Enter a valid date (YYYY-MM-DD)." "invalid date_fixed shows inline error"

# Bad file on fix-proof upload leaves existing screenshot alone
resp=$(req POST "/admin/edit.php?slug=2026-06-01-zed-co" \
    --form-string "status=fixed" \
    -F "screenshot_fix_proof=@$TMP_UPLOADS/bad.pdf;type=application/pdf")
assert_contains "$resp" "File must be a JPG, PNG, or WEBP image." "bad fix-proof upload shows inline error"
stillsame=$(sql "SELECT screenshot_fix_proof FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$stillsame" "2026-06-01-zed-co-fix-proof.png" "failed fix-proof upload leaves existing screenshot in place"

# "after" screenshot upload
resp=$(req POST "/admin/edit.php?slug=2026-06-01-zed-co" \
    --form-string "status=fixed" \
    -F "screenshot_after=@$TMP_UPLOADS/valid.png;type=image/png")
if [ -f "$SCREENSHOTS_DIR/2026-06-01-zed-co-after.png" ]; then
    pass "after screenshot saved with slug-prefixed filename"
else
    fail "after screenshot saved with slug-prefixed filename"
fi

# Non-editable fields can't be tampered with via extra POST params
resp=$(req POST "/admin/edit.php?slug=2026-06-01-zed-co" \
    --form-string "status=fixed" \
    --form-string "company=Hacked Co" \
    --form-string "page_url=https://evil.example.com" \
    --form-string "issue_title=Hacked title")
tampered_company=$(sql "SELECT company FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$tampered_company" "Zed Co" "company can't be changed via edit.php POST params"
tampered_url=$(sql "SELECT page_url FROM cases WHERE slug='2026-06-01-zed-co';")
assert_eq "$tampered_url" "https://zed.example.com/pricing" "page_url can't be changed via edit.php POST params"

# wont_fix status (fourth status; apostrophe in "Won't Fix" label — escaping check)
resp=$(req POST "/admin/edit.php?slug=2026-06-01-zed-co" \
    --form-string "status=wont_fix" --form-string "date_fixed=2026-06-10")
assert_eq "$(status_of "$resp")" "302" "wont_fix status update redirects (302)"
resp=$(req GET "/case.php?slug=2026-06-01-zed-co")
assert_contains "$resp" "badge-wont_fix" "case page shows Won't Fix badge CSS class"
assert_contains "$resp" "Won&#039;t Fix" "Won't Fix label's apostrophe is escaped correctly"

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo
echo "== Summary =="
echo "  $PASS passed, $FAIL failed"
if [ "$FAIL" -gt 0 ]; then
    echo
    echo "Failed:"
    for f in "${FAILURES[@]}"; do
        echo "  - $f"
    done
    exit 1
fi
exit 0
