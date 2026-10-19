#!/usr/bin/env bash
# One load-test run: fresh sign-in tokens, the k6 scenario, and a CPU / connection sample taken while it runs.
#   load/run-one.sh 500 [label]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VUS="${1:-200}"; LABEL="${2:-run}"
RUN="${LOADTEST_DIR:-$HOME/.cache/nailpolish-loadtest}"
K6="${K6_BIN:-k6}"
BASE="${BASE:-http://127.0.0.1:8081}"
SCHEMA="${DB_SEARCH_PATH:-loadtest}"

( cd "$ROOT" && DB_SEARCH_PATH="$SCHEMA" php artisan db:seed --class=LoadTestSeeder --force >/dev/null )
mkdir -p "$RUN/results"
( vmstat 5 > "$RUN/results/$LABEL.vmstat" & echo $! > "$RUN/vmstat.pid" )
sleep 1
cd "$ROOT/load"
"$K6" run -q -e BASE="$BASE" -e VUS="$VUS" -e RAMP="${RAMP:-45s}" -e HOLD="${HOLD:-90s}" \
  -e SUMMARY_FILE="$RUN/results/$LABEL.json" k6-scenario.js 2>&1 | grep -vE "^running|^visitors|^$" || true
kill "$(cat "$RUN/vmstat.pid")" 2>/dev/null || true
awk 'NR>2 {u+=$13; s+=$14; i+=$15; w+=$16; n++} END {printf "cpu during run (avg): user %.0f%%  system %.0f%%  idle %.0f%%  iowait %.0f%%\n", u/n, s/n, i/n, w/n}' "$RUN/results/$LABEL.vmstat"
