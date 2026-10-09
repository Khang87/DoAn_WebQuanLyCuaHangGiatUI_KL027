# Remaining testcase verification

Scope: the 44 Pending cases and cases 215, 236 and 237 in TESTCASES.md at main 8433e9a.

The existing testcase steps and expected outcomes are the specification. Preserve exact three-key pricing, inspection-first creation, historical employee assignments, legacy receiving, financial boundaries and OTP namespaces.

1. Compare Live base-table catalog through read-only SELECTs. Update only the local schema.sql reference; compile its table/constraint portion in a disposable PostgreSQL database. Do not run this snapshot on Live.
2. Exercise missing request/service variants with PHPUnit fixtures. Exercise UI selection, submission and persistence through Chromium, real local sessions, CSRF and disposable PostgreSQL. Use storage test doubles only for the isolated avatar case.
3. Check production guest routes through the available Vercel connector. Run authenticated production avatar upload only through an allowed HTTP route, using the supplied account without recording its credentials. Preserve the existing avatar.
4. Keep a case Pending or Blocked if its full expected result cannot be observed. Record a Failed result for reproducible application defects until corrected and rerun. Never infer full coverage from related tests.
5. Update the nine-column Markdown tables and README counts from completed executions. Run the repository verification commands and CI before merge.

Commands: `node --test tests/Frontend/*.test.mjs`; `npm run build`; `PHP_BIN=php bash scripts/test-postgres.sh`; `PHP_BIN=php bash scripts/test-web-e2e.sh`; `php vendor/bin/phpunit --fail-on-warning --fail-on-risky`. This environment uses the existing PHP 8.4 Docker wrappers because PHP is not installed on the host.

Network limitation: the current environment permits package/GitHub destinations but excludes the production Vercel and Supabase Storage hosts. The Vercel connector supports GET only; it cannot submit the application login or upload. Do not bypass the enforced network policy.
