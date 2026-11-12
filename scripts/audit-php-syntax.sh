#!/usr/bin/env bash
# Repository-wide PHP syntax audit. Read-only; run from the repository root.
set -uo pipefail
cd "$(dirname "$0")/.."
output="${1:-storage/app/performance-audit/php-lint.tsv}"
mkdir -p "$(dirname "$output")"
printf 'status\tfile\tmessage\n' > "$output"
passed=0
failed=0
while IFS= read -r -d '' file; do
  # Ignore the historical VortexOps-Dev snapshot; it is not the live app.
  case "$file" in VortexOps-Dev/*) continue ;; esac
  if result=$(php -l "$file" 2>&1); then
    printf 'PASS\t%s\t\n' "$file" >> "$output"
    passed=$((passed + 1))
  else
    printf 'FAIL\t%s\t%s\n' "$file" "$(printf '%s' "$result" | tr '\t\r\n' '   ')" >> "$output"
    failed=$((failed + 1))
  fi
done < <(git ls-files -z -- '*.php')
printf 'PHP lint: %s passed, %s failed. Report: %s\n' "$passed" "$failed" "$output"
if (( failed > 0 )); then exit 1; fi
