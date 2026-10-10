#!/usr/bin/env bash
set -euo pipefail
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
python3 "$root/scripts/schema/render.py" --check
schema_runtime=$(mktemp -d /tmp/laundry-schema-XXXXXXXX)
schema_container=''
cleanup() {
    if [[ -n "$schema_container" ]]; then docker rm -f "$schema_container" >/dev/null; fi
    rm -rf -- "$schema_runtime"
}
trap cleanup EXIT
# No network, ports, production credentials or live data.
schema_container=$(docker run -d --network none -e POSTGRES_HOST_AUTH_METHOD=trust \
    --mount "type=bind,src=$root,dst=/checkout,readonly" postgres:17 -c wal_level=logical)
ready=false
for ((attempt=0; attempt<60; attempt++)); do
    if docker exec "$schema_container" pg_isready -h 127.0.0.1 -U postgres >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
[[ $ready == true ]] || { echo 'Schema PostgreSQL startup timeout' >&2; exit 1; }
docker exec "$schema_container" psql -X -U postgres -v ON_ERROR_STOP=1 \
    -f /checkout/tests/Postgres/schema-platform-stubs.sql \
    -f /checkout/schema.sql > "$schema_runtime/restore.log"
docker exec "$schema_container" psql -X -A -t -U postgres -v ON_ERROR_STOP=1 \
    -f /checkout/database/schema/catalog-query.sql > "$schema_runtime/restored.json"
python3 "$root/scripts/schema/compare.py" "$schema_runtime/restored.json"
docker exec "$schema_container" psql -X -U postgres -v ON_ERROR_STOP=1 \
    -f /checkout/tests/Postgres/schema-security-contract.sql
echo 'PASS: sessions, profile/read-state column grants and private channel ACL boundaries'
