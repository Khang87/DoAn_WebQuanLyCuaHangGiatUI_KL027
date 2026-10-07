#!/usr/bin/env bash
set -euo pipefail
base=${BASE_COMMIT:-origin/main}
if [[ -z $base || $base =~ ^0+$ ]]; then
    base=$(git hash-object -t tree /dev/null)
fi
git cat-file -e "$base^{tree}"
files=()
while IFS= read -r -d '' file; do
    [[ $file == *.php ]] && files+=("$file")
done < <(git diff --name-only -z --diff-filter=ACMR "$base" HEAD -- app bootstrap config routes tests)
if ((${#files[@]})); then
    php vendor/bin/pint --test "${files[@]}"
else
    echo 'No changed PHP files to format-check.'
fi
