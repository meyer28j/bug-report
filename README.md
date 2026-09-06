# The Bug Report

Log of bugs I've found on live sites, with status and fixes I've suggested. PHP + SQLite.

## Stack

- PHP + PDO/SQLite
- No JS framework, plain CSS

## Structure

- `index.php` - public feed of cases
- `case.php` - individual case detail page
- `admin/new.php` - entry form (local-network only, no auth)
- `includes/` - DB connection, helpers
- `screenshots/` - before/after images per case

See `doc/HANDOFF.md` for full project spec.

## License

MIT
