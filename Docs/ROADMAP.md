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
- [x] `StageGenerationService::resolveSequence()` adopted by request creation
- [x] PHPUnit feature tests for sequential routing (2 files, 20+ tests)
- [x] Vitest unit tests for frontend components (4 files: DocumentViewer, StaffDashboard x2, RequestTimeline)
- [x] Reopen rejected request endpoint and Student Dashboard wiring
- [x] Reopen/audit-trail feature tests
- [x] Sanctum-token logout and revocation tests
- [x] `forRequest()` route-model binding and timeline tests
- [x] Department-admin gate authorization tests
- [x] Frontend student-level and degree-type enum alignment
- [x] Mark collected endpoint and Student Dashboard wiring
- [x] In-app notification API, lifecycle delivery service, and notification bell wiring

## Immediate Fixes Cleared ✅

All previously listed immediate fixes are complete.

## Next (recommended order)

No remaining tasks in this section. Continue with the Later items.

## Later

7. **Department Admin dashboard wiring**
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

9. **Automated testing gaps**
    - Concurrency test (true multi-connection parallel claim attempt)
    - End-to-end staff requeue test after reopening a stage
    - Attachment security test (ownership enforcement)
    - Student request submission integration test
    - Frontend E2E tests (Cypress or Playwright — not currently installed)

## Blocked

*(Nothing is currently blocked by an external dependency.)*

## Needs Decision

- **Should `RequestStageController::index()`'s dead code branch (the `$docRequest` path) be removed?** It uses the old PHP-level `filter()` approach and would reintroduce the concurrency bug if accidentally triggered. It is currently unreachable from any registered route, but it is confusing and should be cleaned up.

- **Should the project continue toward full feature completeness** (Dept Admin, Super Admin, notifications), or is the current state — core student/staff workflow fully functional with tests — considered sufficient for the learning objective?
