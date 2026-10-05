#!/bin/bash
# Upload limits mirror src/.user.ini, which php -S doesn't read.
cd "$(dirname "$0")/src" && php -d upload_max_filesize=6M -d post_max_size=13M -S 127.0.0.1:8000
