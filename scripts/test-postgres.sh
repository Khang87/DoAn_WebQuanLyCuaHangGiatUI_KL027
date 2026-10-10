#!/usr/bin/env bash
set -euo pipefail

root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
php_bin=${PHP_BIN:-php}
"$php_bin" -r 'if (!extension_loaded("pdo_pgsql")) { fwrite(STDERR, "pdo_pgsql required\n"); exit(1); }'
"$php_bin" "$root/tests/Postgres/safety.php"

# Credentials exist only for this disposable local container, never in Git.
export POSTGRES_PASSWORD
POSTGRES_PASSWORD=$(od -An -N24 -tx1 /dev/urandom | tr -d ' \n')
if [[ ${GITHUB_ACTIONS:-} == true ]]; then printf '::add-mask::%s\n' "$POSTGRES_PASSWORD"; fi
name="laundry-verification-${POSTGRES_PASSWORD:0:12}"
container_id=''
cleanup() {
    if [[ -n "$container_id" ]]; then docker rm -f "$container_id" >/dev/null; fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
container_id=$(docker run -d --name "$name" -e POSTGRES_PASSWORD -e POSTGRES_DB=laundry_rpc_test \
    -p 127.0.0.1::5432 --mount "type=bind,src=$root,dst=/checkout,readonly" postgres:17)
export LAUNDRY_PG_CONTAINER=$container_id
ready=false
for ((attempt=0; attempt<60; attempt++)); do
    if docker exec "$container_id" pg_isready -h 127.0.0.1 -U postgres -d laundry_rpc_test >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
if [[ $ready != true ]]; then echo 'PostgreSQL did not become ready within 60 seconds.' >&2; exit 1; fi
export PG_TEST_HOST=127.0.0.1 PG_TEST_DATABASE=laundry_rpc_test PG_TEST_PASSWORD=$POSTGRES_PASSWORD
PG_TEST_PORT=$(docker port "$container_id" 5432/tcp | sed -n 's/^127\.0\.0\.1://p')
export PG_TEST_PORT
for sql in fixtures.sql business_rules.sql chat_contract.sql message-realtime.sql; do
    docker exec "$container_id" psql -X -U postgres -d laundry_rpc_test -v ON_ERROR_STOP=1 \
        -c "SET plpgsql.check_asserts = on; SET statement_timeout = '30s';" \
        -f "/checkout/tests/Postgres/$sql" > /dev/null
done
echo 'PASS: existing RPC PostgreSQL assertions'
"$php_bin" "$root/tests/Postgres/run.php" "${1:-all}"
