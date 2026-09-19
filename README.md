# The Bug Report

Log of bugs I've found on live sites, with status and fixes I've suggested. PHP + SQLite.

## Stack

- PHP + PDO/SQLite
- No JS framework, plain CSS

## Structure

- `data/` - `cases.db` (gitignored, outside the web server document root, private)
- `src/` - web server document root
  - `schema.sql` - DB schema (run once to create `../data/cases.db`)
  - `index.php` - public feed of cases
  - `case.php` - individual case detail page
  - `admin/new.php` - entry form (local-network only, no auth)
  - `includes/` - DB connection, helpers
  - `screenshots/` - case screenshots (gitignored contents, publicly served)

See `doc/` for the full spec and living plan.

## License

MIT
