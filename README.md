# The Bug Report

Log of bugs I've found on live sites, with status and fixes I've suggested. PHP + SQLite.

## Stack

- PHP + PDO/SQLite
- No JS framework, plain CSS

## Structure

- `src/schema.sql` - DB schema (run once to create `data/cases.db`)
- `src/index.php` - public feed of cases
- `src/case.php` - individual case detail page
- `src/admin/new.php` - entry form (local-network only, no auth)
- `src/includes/` - DB connection, helpers
- `src/data/` - `cases.db` + screenshots (gitignored, private, server-only)

See `doc/HANDOFF.md` for full project spec.

## License

MIT
