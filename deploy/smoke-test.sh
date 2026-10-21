#!/usr/bin/env bash
# Post-deploy smoke test. Usage: ./smoke-test.sh https://storesiphon.com   (also works against staging)
set -uo pipefail
BASE="${1:?base url}"
fail=0

check() { # name, expected, actual
  if [ "$2" == "$3" ]; then echo "  ok    $1"; else echo "  FAIL  $1 (expected $2, got $3)"; fail=1; fi
}
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

echo "Smoke test: $BASE"
check "health endpoint /up"            200 "$(status "$BASE/up")"
check "landing page"                    200 "$(status "$BASE/")"
check "try-it page"                     200 "$(status "$BASE/try")"
check "login page"                      200 "$(status "$BASE/login")"
check "dashboard requires sign-in"      302 "$(status "$BASE/dashboard")"
check "unsigned webhook is rejected"    401 "$(status -X POST -H 'Content-Type: application/json' -d '{"deliveryId":"x","type":"InvoiceSettled"}' "$BASE/webhooks/btcpay")"
check ".env is not served"              404 "$(status "$BASE/.env")"

headers="$(curl -s -D - -o /dev/null "$BASE/")"
for h in content-security-policy x-frame-options x-content-type-options referrer-policy strict-transport-security; do
  if grep -qi "^$h:" <<<"$headers"; then echo "  ok    header $h"; else echo "  FAIL  header $h missing"; fail=1; fi
done

[ $fail -eq 0 ] && echo "All checks passed." || echo "Smoke test FAILED."
exit $fail
