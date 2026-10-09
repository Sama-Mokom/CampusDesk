# CampusDesk — Roadmap

**Last reviewed:** 9 October 2026

## Completed ✅

- [x] System modeling (ERD, state machine, permission matrix)
- [x] Laravel project setup
- [x] All current migrations (30 total) and Eloquent models
- [x] Authentication via Sanctum Bearer tokens
- [x] Core request lifecycle: submit, stage generation, claim, resolve
- [x] Automatic status history via Observer pattern
- [x] Queues & email notifications via Mailtrap
- [x] Rate limiting and API Resources
- [x] Frontend/backend CORS integration
- [x] Axios service layer with auth interceptors
- [x] Auth flow wiring (login, register, route guards)
- [x] Reference data endpoints (faculties, departments, programmes, request types)
- [x] Student request pages — fully wired
- [x] Staff queue and active-case pages — fully wired
- [x] Multi-claim concurrency bug — identified and fixed
- [x] Protected attachment viewing (blob URL pattern)
- [x] Staff request-detail timeline (reused student show() endpoint)
- [x] University of Buea data seeder suite (parsers, factories, automated seeders)
- [x] `StageGenerationService` extracted as a service class
- [x] `StageGenerationService::resolveSequence()` adopted by request creation
- [x] PHPUnit feature tests for sequential routing (2 files, 20+ tests)
- [x] Establish the initial Vitest component baseline (DocumentViewer, staff workflow x2, RequestTimeline)
- [x] Reopen rejected request endpoint and student detail-page wiring
- [x] Reopen/audit-trail feature tests
- [x] Sanctum-token logout and revocation tests
- [x] `forRequest()` route-model binding and timeline tests
- [x] Department-admin gate authorization tests
- [x] Frontend student-level and degree-type enum alignment
- [x] Mark collected endpoint and student detail-page wiring
- [x] In-app notification API, lifecycle delivery service, and notification bell wiring
- [x] Department Admin page wiring: primary-department oversight, workflow-aware claimability, active-stage reassignment, immutable handoff audit records, and receiving-staff notifications
- [x] Super Admin page wiring: protected CRUD, elevation, statistics, request oversight, and request/stage status audit
- [x] Repair the September CI baseline: 54 PHPUnit tests / 354 assertions, 37 Vitest tests, ESLint, `vue-tsc`, and the production build
- [x] Dockerize Laravel/Apache and Vue/Nginx with production-style dependency installation
- [x] Add the local four-service Docker Compose stack: frontend, backend, queue worker, and MySQL 8.4
- [x] Verify service health, same-origin API proxying, database persistence, private attachment persistence, queued mail processing, upload limits, and graceful worker shutdown
- [x] Add GitHub Actions backend and frontend quality gates on Ubuntu 24.04
- [x] Validate backend and frontend Docker image builds on pull requests
- [x] Configure GitHub OIDC authentication to AWS without stored access keys
- [x] Publish commit-SHA-tagged backend and frontend images to immutable Amazon ECR repositories
- [x] Configure least-privilege, pull-only ECR access for the EC2 runtime role
- [x] Deploy a digest-pinned four-service staging stack to Ubuntu 24.04 AMD64 on EC2
- [x] Run staging migrations and the full demonstration seed explicitly
- [x] Create student and Super Admin profiles and verify tunneled SPA access
- [x] Configure frontend Nginx to re-resolve the recreated backend through Docker DNS
- [x] Verify persistent 1 GiB swap and record post-seed host, container, disk, and volume usage
- [x] Add the protected, manually approved GitHub-to-EC2 deployment workflow using OIDC and a restricted SSM document
- [x] Deploy exact ECR digests through a locked host script with one migration run, health checks, smoke tests, and explicit recovery boundaries
- [x] Verify the deployment no-op path by redeploying the already active release
- [x] Add the one-shot attachment-volume initializer and require it before backend and worker startup
- [x] Reject failed attachment writes, clean up partial uploads, and scope download authorization to related users and staff
- [x] Add attachment storage/access regression tests and functionally verify upload and authenticated retrieval in staging
- [x] Implement the Issue #7 shared design system and split all role dashboards into guarded nested pages
- [x] Provision a private versioned recovery bucket, KMS key, encrypted SNS alerts, scoped EC2 permissions, and a missing-heartbeat CloudWatch alarm
- [x] Automate coherent database, attachment, image-reference, checksum, and metadata capture at 00:00 and 12:00 UTC
- [x] Upload and download-verify every recovery artifact with the configured KMS key and S3 version IDs
- [x] Restore-test a recovery set in isolated Docker resources without changing live volumes
- [x] Enable 15-minute backup-freshness monitoring and verify the first unattended timer-triggered backup cycle
- [x] Remove the unreachable request-stage queue branch that used the obsolete PHP-level predecessor filter
- [x] Add permanent account disablement, active-token rejection, credential revocation, enabled-only last-Super-Admin protection, and idempotent enablement
- [x] Add append-only administrative actions with a separate Super Admin-only read API
- [x] Add staging-only retained-persona password rotation and transactional known-demo-credential remediation commands
- [x] Add Super Admin account-state badges, filters, disable confirmation/reason handling, self-disable suppression, and re-enable controls
- [x] Verify the backend security changes with 71 PHPUnit tests / 457 assertions and focused Super Admin UI coverage
- [x] Deploy the account-security release, rotate three retained personas, and remediate 618 remaining known-password staging accounts
- [x] Verify a zero-result follow-up dry run, matching disabled-account/audit counts, retained-role access, application health, and a post-remediation capture and isolated restore

## Immediate Fixes Cleared ✅

All previously listed immediate fixes are complete.

## Next (recommended order)

1. **Staging security and recovery operations**
   - Continue scheduled capture and monitoring; investigate every failed service run and perform periodic isolated restore drills.
   - Treat any future full demonstration seed or pre-remediation restore as requiring the credential-remediation runbook again before broader access.
   - Record deployed image digests, backup IDs, restore evidence, and operator decisions in an approved operational location.

2. **Controlled deployment maintenance**
   - Keep the workflow, protected environment, IAM restrictions, SSM document, and root-owned host script synchronized.
   - Define a repeatable reviewed rollout for `compose.staging.yaml` and host-script changes, which are intentionally not copied by the image deployment workflow.
   - Re-run the idempotent no-op check whenever deployment behavior changes.
   - Exercise the documented deployment failure paths in a deliberately isolated environment; do not use live volumes for a destructive drill.
   - Evaluate Systems Manager Session Manager separately so interactive SSH can eventually be removed.

3. **Public staging readiness**
   - Establish stable addressing and DNS.
   - Add TLS termination and certificate renewal.
   - Expose only reviewed HTTP/HTTPS ports after DNS, TLS, security-group, and final application-origin review.
   - Configure final application origins and verify authentication, CORS, uploads, queue processing, and authorization.

See [CI_CD_SESSION_2_HANDOFF.md](CI_CD_SESSION_2_HANDOFF.md) for the complete implementation and troubleshooting record.

## Later

8. **Additional automated testing gaps**
    - Concurrency test (true multi-connection parallel claim attempt)
    - End-to-end staff requeue test after reopening a stage
    - Invalid and oversized attachment validation tests
    - Broader request-submission routing integration cases beyond the attachment-backed path
    - Frontend E2E tests (Cypress or Playwright — not currently installed)

9. **Administrative action audit expansion**
   - The append-only table, safe account-transition/password/remediation events, and paginated Super Admin-only API are complete and remain separate from request `status_history`.
   - Extend coverage to the remaining Super Admin CRUD and staff elevation/demotion actions.
   - Add a dedicated dashboard view for administrative actions; do not merge these records into the request-status audit UI.

## Future Strategic Initiatives

- **Direct assignment of pending/unclaimed stages:** Intentionally deferred. Staff must continue to use the normal concurrency-safe claim flow; any future direct-assignment feature requires separate workflow and audit design.

These GitHub issues are intentionally deferred. They require design review and must not bypass the current request lifecycle, authorization, or audit-history rules.

10. **Frontend browser acceptance and E2E automation**
    - Complete the routed-page visual and workflow checklist at 375, 768, 1024, and 1440 pixels.
    - Add browser E2E coverage after selecting Playwright or Cypress; keep the existing API and authorization contracts intact.

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

- **Which remaining initiative should follow the account-security work:** broader regression coverage, the administrative action dashboard/coverage expansion, or future payment/support work?
