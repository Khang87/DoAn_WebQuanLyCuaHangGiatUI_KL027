#!/usr/bin/env bash
set -euo pipefail
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
php_bin=${PHP_BIN:-php}
case ${1:-all} in all|setup|performance) ;; *) echo 'Expected all, setup or performance' >&2; exit 2;; esac
if [[ ${1:-all} == performance ]]; then export WEB_E2E_PROFILE=1; else unset WEB_E2E_PROFILE WEB_E2E_QUERY_DELAY_MS; fi
"$php_bin" "$root/tests/E2E/safety.php"
export WEB_E2E_RUNTIME WEB_E2E_KEY WEB_E2E_PASSWORD POSTGRES_PASSWORD
WEB_E2E_RUNTIME=$(mktemp -d /tmp/laundry-e2e-XXXXXXXXXXXX)
container_id='' server_pid=''
cleanup() {
    status=$?
    if [[ $status -ne 0 && -f "$WEB_E2E_RUNTIME/server.log" ]]; then
        node --input-type=module -e 'import {readFileSync} from "node:fs";let s=readFileSync(process.env.WEB_E2E_RUNTIME+"/server.log","utf8").split("\n").filter(l=>!/(Accepted|Closing|\[200\]: GET)/.test(l)).join("\n").slice(-12000);for(const n of ["WEB_E2E_KEY","WEB_E2E_PASSWORD","PG_TEST_PASSWORD"]){if(process.env[n])s=s.replaceAll(process.env[n],"[masked]")}process.stderr.write(s)'
    fi
    if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
    if [[ -n "$container_id" ]]; then docker rm -f "$container_id" >/dev/null; fi
    rm -rf -- "$WEB_E2E_RUNTIME"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
printf 'Owned E2E runtime\n' > "$WEB_E2E_RUNTIME/owner"
mkdir -p "$WEB_E2E_RUNTIME/framework/"{sessions,views,cache/data} "$WEB_E2E_RUNTIME/logs"
WEB_E2E_KEY="base64:$(openssl rand -base64 32)"
WEB_E2E_PASSWORD=$(openssl rand -hex 24)
POSTGRES_PASSWORD=$(openssl rand -hex 24)
if [[ ${GITHUB_ACTIONS:-} == true ]]; then
    printf '::add-mask::%s\n' "$WEB_E2E_PASSWORD" "$POSTGRES_PASSWORD" "$WEB_E2E_KEY"
fi
container_id=$(docker run -d -e POSTGRES_PASSWORD -e POSTGRES_DB=laundry_rpc_test -p 127.0.0.1::5432 --mount "type=bind,src=$root,dst=/checkout,readonly" postgres:17)
ready=false
for ((attempt=0; attempt<60; attempt++)); do
    if docker exec "$container_id" pg_isready -h 127.0.0.1 -U postgres -d laundry_rpc_test >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
[[ $ready == true ]] || { echo 'PostgreSQL startup timeout' >&2; exit 1; }
export PG_TEST_HOST=127.0.0.1 PG_TEST_DATABASE=laundry_rpc_test PG_TEST_PASSWORD=$POSTGRES_PASSWORD PG_TEST_PORT
PG_TEST_PORT=$(docker port "$container_id" 5432/tcp | sed -n 's/^127\.0\.0\.1://p')
docker exec "$container_id" psql -X -U postgres -d laundry_rpc_test -v ON_ERROR_STOP=1 -f /checkout/tests/Postgres/fixtures.sql > /dev/null
port=$(node -e 'const s=require("node:net").createServer();s.listen(0,"127.0.0.1",()=>{console.log(s.address().port);s.close()})')
export WEB_E2E_URL="http://127.0.0.1:$port"
"$php_bin" "$root/tests/E2E/seed.php"
"$php_bin" -S "127.0.0.1:$port" -t "$root/public" "$root/tests/E2E/router.php" > "$WEB_E2E_RUNTIME/server.log" 2>&1 &
server_pid=$!
node --input-type=module -e 'for(let i=0;i<300;i++){try{const r=await fetch(process.env.WEB_E2E_URL+"/login",{signal:AbortSignal.timeout(2000)});if(r.status!==200)throw Error("HTTP startup status "+r.status);process.exit(0)}catch(e){if(i===299)throw e;await new Promise(r=>setTimeout(r,100))}}'
if [[ ${1:-all} == setup ]]; then echo 'PASS: disposable HTTP login page ready'; else
    if [[ -n ${WEB_E2E_ASSET_ARCHIVE:-} ]]; then
        cp -- "$WEB_E2E_ASSET_ARCHIVE" "$WEB_E2E_RUNTIME/swal.tgz"
    else
        curl --fail --silent --show-error --location --max-time 30 --retry 3 --retry-max-time 60 https://registry.npmjs.org/sweetalert2/-/sweetalert2-11.26.4.tgz -o "$WEB_E2E_RUNTIME/swal.tgz"
    fi
    node --input-type=module -e 'import {readFileSync} from "node:fs";import {createHash} from "node:crypto";const a=JSON.parse(readFileSync(process.argv[1]));if("sha512-"+createHash("sha512").update(readFileSync(process.env.WEB_E2E_RUNTIME+"/swal.tgz")).digest("base64")!==a.integrity)throw Error("Official asset integrity mismatch")' "$root/tests/E2E/assets.json"
    tar -xOf "$WEB_E2E_RUNTIME/swal.tgz" package/dist/sweetalert2.all.min.js > "$WEB_E2E_RUNTIME/swal.js"
    node --input-type=module -e 'import {readFileSync} from "node:fs";import {createHash} from "node:crypto";const a=JSON.parse(readFileSync(process.argv[1]));if(createHash("sha256").update(readFileSync(process.env.WEB_E2E_RUNTIME+"/swal.js")).digest("hex")!==a.scriptSha256)throw Error("Script integrity mismatch")' "$root/tests/E2E/assets.json"
    if [[ ${1:-all} == performance ]]; then
        node --test "$root/tests/E2E/performance.test.mjs"
    else
        node --test "$root/tests/E2E/web-postgres.test.mjs"
    fi
fi
