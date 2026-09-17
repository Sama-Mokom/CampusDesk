# CampusDesk — Recent Implementation Updates

This document records the completed data-seeding, request-fixture, staff-test-user, and frontend authentication updates. It supersedes older documentation that describes Tier 2+ seeders, request fixtures, or the notification loop as unimplemented.

## Complete Development Dataset

`php artisan migrate:fresh --seed` now creates a deterministic development dataset in dependency order:

1. Faculties, departments, programmes, and Records Office routing departments.
2. Eighty staff users and eligible students in academic departments.
3. Department-staff memberships, with exactly one primary staff member per department; primary staff are elevated to `dept_admin`.
4. Four request types with resolved Records Office final-stage IDs.
5. Twenty-four seeded requests across `pending`, `in_review`, `forwarded`, `ready`, `rejected`, and `collected` states.
6. Three private-storage attachment fixtures and four in-app demonstration notifications.

Seeded request progression is valid rather than arbitrary: every handled stage uses a staff `users.id` assigned to that stage's department, stage transitions create stage history through `RequestStageObserver`, and parent request transitions create matching parent history records. Seeders intentionally do not invoke the notification service, queue jobs, or email delivery.

The seeder support directory is `database/seeders/Support/`. Its uppercase casing matches the `Database\\Seeders\\Support` namespace and is safe on case-sensitive filesystems.

## Shared Request Creation

`RequestCreationService` is the canonical creation path for a request's initial graph. It validates that the requester has a student profile, creates the request, resolves the request-type sequence, creates pending stages, and records the submitted history entry atomically.

`RequestController::store()` and `RequestSeeder` both use this service. `SeedRequestProgressionService` then advances seeded examples without changing production notification behavior. `RequestFactory` is available for direct base-model test fixtures; it does not fabricate workflow stages.

## Mass-Assignment Policy

`Model::preventSilentlyDiscardingAttributes()` is enabled in `AppServiceProvider`. Unsupported mass-assigned attributes now fail immediately instead of disappearing silently.

Reference models that currently have no public write routes use `$guarded = []`: `Faculty`, `Department`, `Programme`, and `RequestType`. Models involving identity, roles, ownership, workflow state, files, notifications, or audit history retain explicit `$fillable` allow-lists. If future admin CRUD endpoints are introduced for reference data, those models must be reviewed and either receive explicit allow-lists or be called only with validated, allow-listed payloads.

## Staff Test Users

Public registration continues to create students only. The complete local-only workflow for plain staff, department admins, super admins, multi-department assignment, verification, and deletion is documented in [TINKER_STAFF_USERS.md](TINKER_STAFF_USERS.md).

## Notification Request Loop Fix

The global notification bell previously mounted for guests, requested the protected `/notifications` endpoint, received 401, and triggered a hard `/login` navigation through the Axios interceptor. That remounted the page and could repeat indefinitely.

The fix has three safeguards:

1. `App.vue` only renders `NotificationBell` when `isAuthenticated` is true.
2. `NotificationBell` and `fetchNotifications()` do not issue notification requests without authentication/token state.
3. The Axios 401 handler clears an existing stale session and redirects only when a token was present and the browser is not already on `/login`.

Guests can now visit `/login` and `/register` without any notification request. Authenticated users retain notification loading and stale-token logout behavior.

## Verification

The following automated coverage was added:

- `RequestTypeSeederTest`: validates all request-type names, descriptions, cast sequences, final department IDs, and idempotent reruns.
- `DatabaseSeederIntegrationTest`: validates the complete seed graph, department records coverage, staff assignments, student eligibility, request lifecycle distribution, histories, attachments, and notifications.
- `NotificationBell.spec.ts`: verifies guests make no notification request and authenticated users do.

Run the full checks with:

```bash
cd campusdesk
php artisan test

cd ../Frontend
npm test
npm run build
```
