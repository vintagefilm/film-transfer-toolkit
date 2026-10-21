#!/bin/bash
# 1.5.1 clip rounding: PHP checks + PHP/JS parity. Usage: bash tests/v151-tests.sh /path/to/wp
WP=${1:-/workspace/wp}; D=$(cd "$(dirname "$0")" && pwd); export FTT_CASES=$(mktemp /tmp/ftt151-XXXX.json)
(cd "$WP" && wp eval-file "$D/v151-tests.php" 2>&1 | grep -v "^Notice\|^Deprecated")
node "$D/clip-rounding-parity.js" "$FTT_CASES"; rc=$?; rm -f "$FTT_CASES"; exit $rc
