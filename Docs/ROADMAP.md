# CampusDesk — Roadmap

## Completed ✅

- [x] System modeling (ERD, state machine, permission matrix)
- [x] Laravel project setup
- [x] All migrations (27 total) and Eloquent models
- [x] Authentication via Sanctum Bearer tokens
- [x] Core request lifecycle: submit, stage generation, claim, resolve
- [x] Automatic status history via Observer pattern
- [x] Queues & email notifications via Mailtrap
- [x] Rate limiting and API Resources
- [x] Frontend/backend CORS integration
- [x] Axios service layer with auth interceptors
- [x] Auth flow wiring (login, register, route guards)
- [x] Reference data endpoints (faculties, departments, programmes, request types)
- [x] Student Dashboard — fully wired
- [x] Staff Dashboard — fully wired
- [x] Multi-claim concurrency bug — identified and fixed
- [x] Protected attachment viewing (blob URL pattern)
- [x] Staff request-detail timeline (reused student show() endpoint)
- [x] University of Buea data seeder suite (parsers, factories, automated seeders)
- [x] `StageGenerationService` extracted as a service class
- [x] PHPUnit feature tests for sequential routing (2 files, 20+ tests)
- [x] Vitest unit tests for frontend components (4 files: DocumentViewer, StaffDashboard x2, RequestTimeline)

## Immediate Fixes Required 🔴

These are active bugs that should be fixed before extending the system further:

1. **Fix logout endpoint** — replace session-based code with `currentAccessToken()->delete()` in `AuthenticatedSessionController::destroy()`. See KNOWN_ISSUES.md.

2. **Fix `forRequest()` route-model binding** — rename `{request}` parameter in the route to `{docRequest}`. See KNOWN_ISSUES.md.

3. **Fix `is_dept-admin` gate name** — rename to `'is-dept-admin'` (all hyphens) in `AppServiceProvider::boot()`. See KNOWN_ISSUES.md.

4. **Fix frontend `level` and `DegreeType` type values** — update `RegisterCredentials.level` and `StudentLevel` in `types/index.ts` to use `'100'`–`'600'`; update `DegreeType` to `'BACHELOR' | 'CERTIFICATE' | 'MASTER' | 'PHD'`. See KNOWN_ISSUES.md.

## Next (recommended order)

4. **Reopen request endpoint**
   - Backend: `POST /api/requests/{request}/reopen`
   - Use `StageGenerationService` (already built — `app/Services/StageGenerationService.php`) rather than duplicating `resolveSequence()` logic
   - Set `is_reopened = true`, `status = pending`, spawn fresh stages, log status history
   - Frontend: wire `doReopen()` in `StudentDashboard.vue`

5. **Mark collected endpoint**
   - Backend: `PATCH /api/requests/{request}/collect`
   - Verify `status === 'ready'`, set `status = 'collected'`, log status history
   - Frontend: wire `doCollected()` in `StudentDashboard.vue`

6. **In-app notifications**
   - Backend: `GET /api/notifications`, `PATCH /api/notifications/{id}/read`
   - Consider: should the email-notification observer also write to the `notifications` table? (Currently it does not — they are disconnected.)
   - Frontend: wire `NotificationBell.vue` (currently uses `useMockData`)
   - Add `$fillable` to `Notification` model before attempting to create rows

## Later

7. **Department Admin dashboard wiring**
   - Fix the `is_dept-admin` gate first (item 3 above)
   - Backend: `GET /api/dept-admin/requests` (all requests in primary department, claimed + unclaimed)
   - Backend: `PATCH /api/dept-admin/stages/{stage}/reassign` (change `handled_by`)
   - Frontend: replace `useMockData` references in `DeptAdminView.vue` with real service calls

8. **Super Admin dashboard wiring**
   - This is the largest remaining chunk of work
   - CRUD endpoints for: faculties, departments, programmes, request types, users
   - Staff elevation endpoint (assign `admin_level`)
   - System-wide stats endpoint
   - Full audit log endpoint (paginated `status_history`)
   - Frontend wiring of `AdminDashboard.vue` (the mock UI already has a detailed structure)

9. **Refactor `RequestController::resolveSequence()`**
   - `StageGenerationService` already exists with the same logic, more cleanly structured
   - Remove the inline `resolveSequence()` private method from `RequestController`
   - Inject `StageGenerationService` and use it in both `store()` and the new `reopen()` endpoint
   - This is a tech debt item, not a blocking issue

10. **Automated testing gaps**
    - Concurrency test (true multi-connection parallel claim attempt)
    - Status history FK correctness test (`changed_by` → `staff_profiles.id`)
    - Attachment security test (ownership enforcement)
    - Student request submission integration test
    - Frontend E2E tests (Cypress or Playwright — not currently installed)

## Blocked

*(Nothing is currently blocked by an external dependency.)*

## Needs Decision

- **Should `status_history.changed_by` be refactored to reference `users.id` instead of `staff_profiles.id`?** (See DECISIONS.md ADR-02.) This has caused repeated bugs. A decision to simplify vs. keep the current semantic distinction should be made before building more features that write to `status_history`.

- **Should `RequestStageController::index()`'s dead code branch (the `$docRequest` path) be removed?** It uses the old PHP-level `filter()` approach and would reintroduce the concurrency bug if accidentally triggered. It is currently unreachable from any registered route, but it is confusing and should be cleaned up.

- **Should the project continue toward full feature completeness** (Dept Admin, Super Admin, notifications), or is the current state — core student/staff workflow fully functional with tests — considered sufficient for the learning objective?
