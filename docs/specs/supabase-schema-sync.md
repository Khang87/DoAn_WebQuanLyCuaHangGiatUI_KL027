# Supabase schema synchronization

## Objective and source
Synchronize the application's PostgreSQL schema with the read-only catalog of
Supabase project `osnblefzulmsuthhuswl` (PostgreSQL 17.6), captured 2026-10-10.
Laravel 13 continues to own web validation and authorization; mobile RPC contracts
and legacy compatibility remain intact. No production DDL or row export is needed.

## Scope and acceptance
- Inventory every non-system schema's tables/views, columns, constraints, indexes,
  RLS flags and policies in a metadata-only catalog.
- Generate `schema.sql` for all public/private application objects: sequences,
  tables, views, routines, triggers, indexes, policies, grants, default privileges
  and publication membership, in dependency-safe order.
- Include Storage policies customized by this application. Supabase-managed Auth,
  Storage, Realtime, extension and migration tables remain platform prerequisites,
  recorded in the catalog rather than recreated as Laravel application tables.
- Restore on disposable PostgreSQL 17 with explicitly labeled platform test stubs;
  compare the restored application catalog with the captured source.
- Compare every application Eloquent model with the catalog. Add writable fields
  only where the module's validated input contract needs them. Identities, totals,
  ownership, audit, tokens and internal registry/quote fields stay system-managed.
- Repair demonstrated missing consumers (including mobile customer avatar fields)
  and verify affected behavior plus existing regression tests.

## Implementation and verification
1. Capture catalog plus expanded ACL entries using read-only SQL; preserve exact
   names, types, identity/default expressions and legacy columns.
2. Render reproducibly from that catalog. Never rewrite historical production
   migrations or create duplicate DDL for structures already present on Supabase.
3. Audit Models/Requests/Controllers/Resources/forms, record intentional omissions.
4. Restore and compare structural metadata, test changed data consumers, run CI.
5. Push and merge after all required checks pass (previous user authorization).

## Boundaries
No production writes, data dump, secrets, blanket mass assignment, new CRUD for
internal tables, altered RLS rules, or changed financial calculations.
`schema.sql` is a fresh-project snapshot, not an upgrade script for an existing DB.

## Sources
- https://supabase.com/docs/guides/platform/migrating-within-supabase/backup-restore
  (application schema dump; Auth/Storage customizations restored separately).
- https://www.postgresql.org/docs/17/functions-info.html
  (catalog definition functions).
- https://laravel.com/docs/13.x/eloquent#mass-assignment
  (explicit writable attribute boundary).
