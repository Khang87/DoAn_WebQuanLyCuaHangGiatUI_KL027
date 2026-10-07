# Capability Map: Order and delivery contracts

Status: scope approved by user; implementation, push and merge explicitly authorized on 2026-10-07.
Baseline: 573a5102b9df908882b832a050126c90594efa56.

## Objective

Resolve the confirmed Bug.md findings without changing the Web-as-Source-of-Truth architecture. Preserve Laravel/Blade, existing resource routes, server-authoritative pricing, database schema, financial rules, and historical Pending order inspection.

## Assumptions for review

- This is a correction to the existing Web application, not a new API or frontend rewrite.
- Order item prices remain determined by the server; there is no new price-override feature.
- Delivery planning and delivery execution are separate actions. A pending home-return slip may exist before washing is complete and without a schedule.
- Selecting an employee for new work requires an active employee. Historical attribution must remain readable; editing an unrelated field should not silently replace it.
- No database migration, additional dependency, or production data operation is included in this scope.

## Modules

| Module id | Responsibility and Bug.md trace | Depends on |
|---|---|---|
| order-pricing | Preserve service/garment/unit choices; align create/edit pricing controls and displayed totals with server prices; add UI regression coverage (1, 2, 9). | Existing PricingService contract |
| active-assignment | Validate active employee assignments at request and callable service boundaries; align available form choices (5, 7). | Existing employee status model |
| delivery-contract | Protect CRUD against cancelled/settled orders and conflicting delivery legs; distinguish planning from execution; support unscheduled pending returns (3, 4). | active-assignment; existing order lifecycle |
| booking-snapshot | Review the lock/snapshot boundary and harden it only where justified, preserving unsaved inspected booking changes, idempotency and atomic effects (6). | order-pricing, active-assignment; existing BookingService transaction |

Build order: order-pricing -> active-assignment -> delivery-contract -> booking-snapshot.

These are work boundaries, not new packages or mandatory abstractions. Provider contracts belong in their module specifications; consumers reuse them.

## Contract decisions to resolve in specifications

- Delivery duplication: distinguish conflicting active slips from cancelled history and legitimate redelivery; do not impose one lifetime slip per leg without evidence.
- Delivery execution: determine allowed transitions using existing order/delivery enums and lifecycle rules, without prohibiting advance planning.
- Delivery schedule: allow an absent schedule for pending returns; require a complete date/time pair when scheduling and define edits to already-scheduled records.
- Employee assignment: distinguish a new/reassigned employee from preserved historical attribution, consistently in UI and services.
- Booking lock: preserve the inspected changes currently filled by BookingService before they are persisted; never replace that snapshot blindly with a database reload.

## Verification expectations

Each module specification must define focused regression tests and the relevant UI/manual checks before implementation. Verify multi-unit selection and saved totals together, direct service calls and form requests, valid/invalid delivery transitions, and booking conversion retaining edited addresses/methods. Run the project test suite, formatting, frontend build and Blade compilation after integrated changes. Do not claim PostgreSQL concurrency or browser coverage from SQLite/PHP assertions alone.

## Explicitly preserved

- completeReceivingInspection() remains available for historical Pending orders (8).
- Payment, promotion and reward-point semantics remain owned by existing services.
- Existing App contracts and routes remain compatible; Web holds business authority.
- Behavior-preserving cleanup is separated from bug-fix commits and limited to touched code.

## Workflow gate

Scope approved in conversation. User subsequently instructed autonomous implementation, push and merge. Module specs and plans are recorded before code; verification remains mandatory before publication.
