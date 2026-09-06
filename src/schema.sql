CREATE TABLE cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE NOT NULL,          -- e.g. "2026-09-06-acme-corp"
    company TEXT NOT NULL,
    page_url TEXT NOT NULL,
    issue_title TEXT NOT NULL,          -- 1-line summary
    issue_description TEXT,
    status TEXT NOT NULL DEFAULT 'reported', -- reported/acknowledged/fixed/no_response/wont_fix
    reported_via TEXT,                  -- email/form/twitter/etc

    date_reported TEXT NOT NULL,        -- ISO 8601 dates stored as TEXT
    date_acknowledged TEXT,
    date_fixed TEXT,

    screenshot_bug TEXT,                -- file path, relative to data/screenshots/ — the original bug
    screenshot_fix_proof TEXT,          -- optional — dev-tools live edit demonstrating the suggested fix
    screenshot_after TEXT,              -- optional — real deployed fix, once shipped
    suggested_fix TEXT,                 -- raw code snippet/diff text

    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
