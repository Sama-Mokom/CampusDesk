# CampusDesk

At the University of Buea, requesting an official document can mean submitting the required information and then losing sight of the process. A transcript may be moving between offices, waiting for review, or blocked by a missing detail—but the student often has no way to know. Finding out can require repeated trips to the records office, and a silent delay becomes especially costly when a scholarship, university application, internship, or job deadline is approaching.

CampusDesk was built by [Nkeng Sama Mokom](https://samamokom.vercel.app/writing/why-i-built-campusdesk) in response to that experience. It gives students a visible path from submission to collection and gives university staff a structured way to receive, claim, review, approve, reject, and forward requests. Every transition is recorded; rejected requests include a reason and can be reopened; students are notified when something changes or when a document is ready.

The result is a working university document-request and tracking system designed around a practical institutional need: replacing uncertainty with visibility, accountability, and a clear next action.

## What CampusDesk changes

- **For students:** submit requests and supporting documents online, follow each stage, see when action is required, and know when a document is ready for collection.
- **For staff:** work from an organised departmental queue, claim cases safely, and move requests through a defined multi-office route without duplicate handling.
- **For administrators:** manage the people, departments, request types, and routing rules behind the process while retaining an auditable history of request activity.

CampusDesk currently implements the core student, staff, department-admin, and system-admin workflows. It runs in a private AWS staging environment while public-access controls and further operational hardening continue. The system is not yet an official University of Buea service; it is being developed with real institutional adoption in mind.

## Documentation

If you are evaluating, operating, or extending CampusDesk, use the documents below in this order:

### 1. Start Here

**[HANDOFF.md](./Docs/HANDOFF.md)** — Read this first. It summarizes the merged application, delivery, and recovery state; the next work; critical constraints; and known gotchas.

### 2. Understand the Project

- **[PROJECT_OVERVIEW.md](./Docs/PROJECT_OVERVIEW.md)** — What CampusDesk is, why it exists, who it's for, project goals and constraints
- **[PROJECT_HISTORY.md](./Docs/PROJECT_HISTORY.md)** — Chronological evolution of the project from idea to current state

### 3. Understand the System

- **[ARCHITECTURE.md](./Docs/ARCHITECTURE.md)** — Overall system architecture, frontend/backend structure, auth architecture, file storage, queue architecture
- **[DATABASE.md](./Docs/DATABASE.md)** — Full schema, ER diagram, table definitions, state machines, design decisions on relationships
- **[TECH_STACK.md](./Docs/TECH_STACK.md)** — Every technology used, why, and its implementation status

### 4. Understand What's Built

- **[FEATURES.md](./Docs/FEATURES.md)** — Every feature documented in detail: purpose, flow, validation, database interactions, implementation status
- **[IMPLEMENTATION_UPDATES.md](./Docs/IMPLEMENTATION_UPDATES.md)** — Seeder, request-fixture, authentication, staging, and attachment implementation corrections
- **[USER_FLOWS.md](./Docs/USER_FLOWS.md)** — Roles, permission matrix, sequence diagrams, state machine diagrams
- **[API.md](./Docs/API.md)** — Every endpoint: method, auth requirements, request/response shapes, validation rules, side effects
- **[UI_UX.md](./Docs/UI_UX.md)** — Design system, key screens, component inventory
- **[FRONTEND_REDESIGN.md](./Docs/FRONTEND_REDESIGN.md)** — Issue #7 implementation history, API compatibility, automated verification, merge record, and outstanding browser acceptance checks

### 5. Understand Why Things Are the Way They Are

- **[DECISIONS.md](./Docs/DECISIONS.md)** — Architecture Decision Records for every significant technical choice, with context and consequences
- **[KNOWN_ISSUES.md](./Docs/KNOWN_ISSUES.md)** — Significant bugs, their root causes, fixes, and preventive guidance. **Read this before touching related code.**

### 6. Continue Development

- **[ROADMAP.md](./Docs/ROADMAP.md)** — What's completed, what's next, what's blocked, what needs a decision
- **[SECURITY.md](./Docs/SECURITY.md)** — Auth mechanism, authorization layers, known security gaps
- **[TESTING.md](./Docs/TESTING.md)** — Current test coverage and recommended test checklist
- **[DEVELOPMENT_SETUP.md](./Docs/DEVELOPMENT_SETUP.md)** — Full environment setup, commands, troubleshooting
- **[TINKER_STAFF_USERS.md](./Docs/TINKER_STAFF_USERS.md)** — Development-only plain-staff, department-admin, and Super Admin account recipes
- **[frontend_integration_specs.md](./Frontend/Documentation/frontend_integration_specs.md)** — Current Vue service, route, auth, and module integration reference
- **[CI_CD_SESSION_2_HANDOFF.md](./Docs/CI_CD_SESSION_2_HANDOFF.md)** — Comprehensive local Docker, CI, immutable ECR, AWS staging, protected SSM deployment, attachment hardening, operations, troubleshooting, and recovery guide
- **[STAGING_RECOVERY.md](./Docs/STAGING_RECOVERY.md)** — Installation, scheduled capture, monitoring, isolated restore, and incident-controlled live-restore boundaries
- **[CI-CD.md](./CI-CD.md)** — CI/CD implementation status and next operational milestone

### 7. Historical Design Records

These files explain how earlier work was planned; their headers identify them as historical and link back to the current contract:

- **[SUPER_ADMIN_IMPLEMENTATION_PLAN.md](./Docs/SUPER_ADMIN_IMPLEMENTATION_PLAN.md)** — original Super Admin implementation plan
- **[campusdesk_seeder_specification.md](./campusdesk/Documentation/campusdesk_seeder_specification.md)** — July 2026 seeder design specification

## Diagrams

All diagrams are embedded as Mermaid code within the relevant document (primarily DATABASE.md and USER_FLOWS.md) rather than as separate files, since Mermaid diagrams are most useful in context alongside their explanation.

---

## Quick Facts

| | |
|---|---|
| **Backend** | Laravel 12, PHP 8.3 container runtime, MySQL, Sanctum 4.x (Bearer tokens) |
| **Frontend** | Vue 3, TypeScript, Vite 6, Tailwind + DaisyUI, Axios |
| **Backend location** | `campusdesk/` |
| **Frontend location** | `Frontend/src/` |
| **Backend URL (bare dev)** | `http://127.0.0.1:8000` |
| **Frontend URL (bare dev)** | `http://localhost:5173` |
| **Docker Compose URL** | `http://localhost:8080` |
| **Current status** | Application workflows, CI, immutable ECR publication, manually approved SSM deployment, private attachment hardening, encrypted off-host recovery, isolated restore testing, systemd freshness monitoring, and staging credential remediation are implemented. Public DNS/HTTPS and broader administrative-action audit coverage remain open. |
| **Automated checks** | GitHub Actions runs backend PHPUnit, frontend Vitest/ESLint/TypeScript/build checks, Compose validation, pull-request image builds, and development-branch image publication. On 7 October 2026 the local suites passed with 58 PHPUnit tests/373 assertions and 132 Vitest tests across 14 files. See [TESTING.md](./Docs/TESTING.md) and the latest CI run. |

---

## Source of Truth Note

The active documentation was reconciled against the `development` source tree, automated suites, merged pull-request history, and supplied staging evidence on 7 October 2026. Source code and executable configuration remain authoritative when they conflict with prose. Historical design artifacts are labeled as such and must not be used as current operating instructions. Unresolved work is recorded in [ROADMAP.md](./Docs/ROADMAP.md); deployment and recovery operations are maintained in [CI_CD_SESSION_2_HANDOFF.md](./Docs/CI_CD_SESSION_2_HANDOFF.md) and [STAGING_RECOVERY.md](./Docs/STAGING_RECOVERY.md).
