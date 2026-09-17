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

## Immediate Fixes Cleared ✅

All previously listed immediate fixes are complete.

## Next (recommended order)

No remaining tasks in this section. Continue with the Later items.

## Later

7. **Super Admin dashboard wiring**
   - This is the largest remaining chunk of work
   - CRUD endpoints for: faculties, departments, programmes, request types, users
   - Staff elevation endpoint (assign `admin_level`)
   - System-wide stats endpoint
   - Full audit log endpoint (paginated `status_history`)
   - Frontend wiring of `AdminDashboard.vue` (the mock UI already has a detailed structure)

8. **Automated testing gaps**
    - Concurrency test (true multi-connection parallel claim attempt)
    - End-to-end staff requeue test after reopening a stage
    - Attachment security test (ownership enforcement)
    - Student request submission integration test
    - Frontend E2E tests (Cypress or Playwright — not currently installed)

## Future Strategic Initiatives

- **Direct assignment of pending/unclaimed stages:** Intentionally deferred. Staff must continue to use the normal concurrency-safe claim flow; any future direct-assignment feature requires separate workflow and audit design.

These GitHub issues are intentionally deferred. They require design review and must not bypass the current request lifecycle, authorization, or audit-history rules.

10. **Frontend design-system overhaul**
    - Translate approved Figma mockups into the active Vue 3 SPA under `Frontend/src/`; do not revive the abandoned Next.js scaffold.
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

- **Should the project continue toward full feature completeness** (Dept Admin, Super Admin, notifications), or is the current state — core student/staff workflow fully functional with tests — considered sufficient for the learning objective?
