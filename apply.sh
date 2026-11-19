#!/bin/bash
# Run from the root of your To-do-list clone, after copying these files over it.
set -e
while IFS= read -r f; do
  [ -n "$f" ] && git rm -q -f --ignore-unmatch -- "$f"
done < files-to-delete.txt
rm -f files-to-delete.txt apply.sh
git add -A
git status --short
echo "Done. Review with 'git status', then commit."
