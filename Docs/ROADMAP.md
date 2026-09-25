# CampusDesk — Roadmap

## Completed ✅

- [x] System modeling (ERD, state machine, permission matrix)
- [x] Laravel project setup
- [x] All migrations (28 total) and Eloquent models
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
- [x] Department Admin dashboard wiring: primary-department oversight, workflow-aware claimability, active-stage reassignment, immutable handoff audit records, and receiving-staff notifications
- [x] Super Admin dashboard wiring: protected CRUD, elevation, statistics, request oversight, and request/stage status audit
- [x] Repair the complete backend and frontend quality gates: 54 PHPUnit tests / 354 assertions, 37 Vitest tests, ESLint, `vue-tsc`, and the production build
- [x] Dockerize Laravel/Apache and Vue/Nginx with production-style dependency installation
- [x] Add the local four-service Docker Compose stack: frontend, backend, queue worker, and MySQL 8.4
- [x] Verify service health, same-origin API proxying, database persistence, private attachment persistence, queued mail processing, upload limits, and graceful worker shutdown

## Immediate Fixes Cleared ✅

All previously listed immediate fixes are complete.

## Next (recommended order)

1. **GitHub Actions continuous integration**
   - Learn workflows, events, jobs, runners, steps, and actions before writing YAML.
   - Add an independent backend job: Composer install and the full PHPUnit suite.
   - Add an independent frontend job: `npm ci`, ESLint, `vue-tsc`, Vitest, and the Vite production build.
   - Trigger on pull requests and appropriate pushes.
   - Keep image publishing and deployment out of the first workflow.
   - Use [CI_CD_SESSION_1_DOCKER.md](CI_CD_SESSION_1_DOCKER.md) as the verified Docker baseline.

## Later

8. **Additional automated testing gaps**
    - Concurrency test (true multi-connection parallel claim attempt)
    - End-to-end staff requeue test after reopening a stage
    - Attachment security test (ownership enforcement)
    - Student request submission integration test
    - Frontend E2E tests (Cypress or Playwright — not currently installed)

9. **Administrative action audit log**
   - Add a dedicated, append-only table for Super Admin CRUD and staff elevation/demotion actions, separate from request `status_history` and stage handoff records.
   - Record actor, action, entity type and ID, timestamp, and appropriate before/after details without storing passwords or tokens.
   - Add a paginated, Super Admin-only API and dashboard view for these events. This is deferred from task 7 and must not be presented as part of its request-status audit log.

## Future Strategic Initiatives

- **Direct assignment of pending/unclaimed stages:** Intentionally deferred. Staff must continue to use the normal concurrency-safe claim flow; any future direct-assignment feature requires separate workflow and audit design.

These GitHub issues are intentionally deferred. They require design review and must not bypass the current request lifecycle, authorization, or audit-history rules.

10. **Frontend design-system overhaul**
    - Translate approved Figma mockups into the Vue 3 SPA under `Frontend/src/`.
    - Establish design tokens, accessible shared components, responsive layouts, loading states, and visual regression coverage.
    - Preserve the existing Vue service contracts unless a separately approved API change is required.

11. **Real-time event delivery**
    - Evolve the current queued `RequestStatusNotificationService` into a domain-event source for request status, stage assignment, and ready-for-collection events.
    - Prefer WebSockets/SSE for first-party SPA updates; evaluate signed outbound webhooks only for genuine third-party consumers.
    - Add queued delivery, exponential backoff, idempotency, delivery audit records, and HMAC verification for outbound webhooks.

12. **Scoped AI support with human escalation**
    - Build an isolated AI gateway with read-only, least-privilege tools and retrieval over approved, versioned support content.
    - Redact PII and credentials before any external-model call; never permit the model to approve requests, change roles, or mutate workflow state.
    - Add support conversations, messages, and handoff tickets, routing escalations to the relevant department admin or super admin with the sanitized transcript.

13. **Mobile Money processing fees**
    - Design-review and select an aggregator (Fapshi is the proposed default; CamPay is the alternative) before implementation.
    - Add fee configuration to request types, a provider-neutral payment gateway interface, payment records, signed webhook processing, and immutable payment/event logs.
    - Model fee-required requests as `awaiting_payment`; use a locked webhook transaction to release only successful payments into the normal queue, while preventing staff claims before payment.

## Blocked

*(Nothing is currently blocked by an external dependency.)*

## Needs Decision

- **Should `RequestStageController::index()`'s dead code branch (the `$docRequest` path) be removed?** It uses the old PHP-level `filter()` approach and would reintroduce the concurrency bug if accidentally triggered. It is currently unreachable from any registered route, but it is confusing and should be cleaned up.

- **Which remaining initiative should follow the completed admin dashboards:** broader regression coverage, the administrative action audit, or future payment/support work?
