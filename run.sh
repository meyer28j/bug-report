#!/bin/bash
cd "$(dirname "$0")" || exit 1
# Build the DB on first run; PDO would otherwise create an empty, table-less file.
mkdir -p data
[ -s data/cases.db ] || sqlite3 -init /dev/null data/cases.db < src/schema.sql
# Upload limits mirror src/.user.ini, which php -S doesn't read.
cd src && php -d upload_max_filesize=6M -d post_max_size=13M -S 127.0.0.1:8000
