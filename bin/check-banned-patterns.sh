#!/bin/bash
# Fails when production code contains a pattern this project has ruled out.
# See "Checks: at the boundary, not everywhere" in CLAUDE.md for the reasons.
# Run by pre-commit and by hand: bin/check-banned-patterns.sh

cd "$(dirname "$0")/.." || exit 1

PHP_FILES="klaro-geo.php uninstall.php includes"
JS_FILES="js"
failed=0

# check <description> <extended regex> <paths...>
check() {
    local description="$1" pattern="$2"
    shift 2
    local matches
    matches=$(grep -rnE "$pattern" "$@" 2>/dev/null)
    if [ -n "$matches" ]; then
        echo "BANNED: $description"
        echo "$matches" | sed 's/^/  /'
        echo
        failed=1
    fi
}

# Production code must not behave differently under test.
check "test-environment branch in PHP (tests adapt to the code, not the reverse)" \
    "WP_TESTS_DOMAIN|WP_TESTS_DIR|PHPUNIT" $PHP_FILES
check "test-environment branch in JavaScript" \
    "process\.env|typeof process|typeof jest|NODE_ENV" $JS_FILES

# The plugin's own files are always loaded; guarding its own functions hides load-order bugs.
check "function_exists() on one of the plugin's own functions" \
    "function_exists\( *'klaro_geo_" $PHP_FILES

# wp_send_json_*() already ends the request.
check "exit or die straight after wp_send_json_*()" \
    "wp_send_json(_success|_error)?\(.*\); *(exit|die)" $PHP_FILES
if grep -rnE -A1 "^\s*wp_send_json(_success|_error)?\(.*\);\s*$" $PHP_FILES | grep -E "^\S+-[0-9]+-\s*(exit|die)\b" >/dev/null; then
    echo "BANNED: exit or die on the line after wp_send_json_*()"
    grep -rnE -A1 "^\s*wp_send_json(_success|_error)?\(.*\);\s*$" $PHP_FILES | grep -E "^\S+-[0-9]+-\s*(exit|die)\b" | sed 's/^/  /'
    echo
    failed=1
fi

# The log helpers add the prefix; a hand-typed one means a helper was bypassed or copied from.
check "hand-typed DEBUG prefix in a log message (klaroGeoLog() adds the prefix)" \
    "['\"]DEBUG:" $JS_FILES

if [ "$failed" -eq 0 ]; then
    echo "No banned patterns."
fi
exit $failed
