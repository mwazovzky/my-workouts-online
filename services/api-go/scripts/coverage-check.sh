#!/bin/sh
# Fails when total statement coverage in a Go coverage profile is below a
# threshold. Blocks from files matching the exclude pattern (generated code)
# are dropped first, so the number measures hand-written code only.
#
# Usage: scripts/coverage-check.sh <profile> <minimum-percent> <exclude-regex>
set -eu

profile=$1
minimum=$2
exclude=$3
filtered="$profile.filtered"

grep -Ev "$exclude" "$profile" > "$filtered"
total=$(go tool cover -func="$filtered" | awk '/^total:/ { sub("%", "", $3); print $3 }')

awk -v total="$total" -v minimum="$minimum" 'BEGIN {
    if (total + 0 < minimum + 0) {
        printf "coverage %.1f%% is below the %s%% minimum (generated code excluded)\n", total, minimum
        exit 1
    }
    printf "coverage %.1f%% (minimum %s%%, generated code excluded)\n", total, minimum
}'
