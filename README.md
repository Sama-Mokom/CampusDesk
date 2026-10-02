# CampusDesk — Documentation Index

This is the complete knowledge base for the CampusDesk project — a university document request and tracking system built with Laravel 12 (backend) and Vue 3 (frontend), developed as a hands-on Laravel-learning project by Nkeng Sama Mokom (University of Buea).

**If you are a new AI agent or developer picking up this project, read documents in this order:**

## 🚨 1. Start Here

**[HANDOFF.md](./Docs/HANDOFF.md)** — Read this FIRST. Current status, last completed action, exact next steps, critical constraints not to violate, and known gotchas.

## 2. Understand the Project

- **[PROJECT_OVERVIEW.md](./Docs/PROJECT_OVERVIEW.md)** — What CampusDesk is, why it exists, who it's for, project goals and constraints
- **[PROJECT_HISTORY.md](./Docs/PROJECT_HISTORY.md)** — Chronological evolution of the project from idea to current state

## 3. Understand the System

- **[ARCHITECTURE.md](./Docs/ARCHITECTURE.md)** — Overall system architecture, frontend/backend structure, auth architecture, file storage, queue architecture
- **[DATABASE.md](./Docs/DATABASE.md)** — Full schema, ER diagram, table definitions, state machines, design decisions on relationships
- **[TECH_STACK.md](./Docs/TECH_STACK.md)** — Every technology used, why, and its implementation status

## 4. Understand What's Built

- **[FEATURES.md](./Docs/FEATURES.md)** — Every feature documented in detail: purpose, flow, validation, database interactions, implementation status
- **[USER_FLOWS.md](./Docs/USER_FLOWS.md)** — Roles, permission matrix, sequence diagrams, state machine diagrams
- **[API.md](./Docs/API.md)** — Every endpoint: method, auth requirements, request/response shapes, validation rules, side effects
- **[UI_UX.md](./Docs/UI_UX.md)** — Design system, key screens, component inventory

## 5. Understand Why Things Are the Way They Are

- **[DECISIONS.md](./Docs/DECISIONS.md)** — Architecture Decision Records for every significant technical choice, with context and consequences
- **[KNOWN_ISSUES.md](./Docs/KNOWN_ISSUES.md)** — Every bug encountered, its root cause, its fix, and the lesson learned. **Read this before touching related code.**

## 6. Continue Development

- **[ROADMAP.md](./Docs/ROADMAP.md)** — What's completed, what's next, what's blocked, what needs a decision
- **[SECURITY.md](./Docs/SECURITY.md)** — Auth mechanism, authorization layers, known security gaps
- **[TESTING.md](./Docs/TESTING.md)** — Current test coverage and recommended test checklist
- **[DEVELOPMENT_SETUP.md](./Docs/DEVELOPMENT_SETUP.md)** — Full environment setup, commands, troubleshooting
- **[CI_CD_SESSION_2_HANDOFF.md](./Docs/CI_CD_SESSION_2_HANDOFF.md)** — Comprehensive local Docker, CI, immutable ECR, AWS staging, protected SSM deployment, attachment hardening, operations, troubleshooting, and recovery guide
- **[CI-CD.md](./CI-CD.md)** — CI/CD learning-track status and next checkpoint

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
| **Current status** | Application workflows, CI, immutable ECR publication, and the manual-approved SSM staging deployment are implemented. The full request lifecycle and private attachment upload/retrieval were functionally verified in staging on 2 October 2026. Backups/restores, public DNS/HTTPS, and the separate administrative action audit remain open. |
| **Automated checks** | GitHub Actions runs backend PHPUnit, frontend Vitest/ESLint/TypeScript/build checks, Compose validation, and image builds. The historical Session 1 baseline was 54 PHPUnit tests/354 assertions and 37 Vitest tests; attachment regression tests were added later. See [TESTING.md](./Docs/TESTING.md) and the latest CI run for current counts. |

---

## Source of Truth Note

This documentation was originally reconstructed from a conversation history record and has since been reconciled against the actual files on disk and the supplied staging evidence. Cross-check against actual source files before making significant changes. The active documentation was last reviewed on 2 October 2026; unresolved work is recorded in [ROADMAP.md](./Docs/ROADMAP.md) and [CI_CD_SESSION_2_HANDOFF.md](./Docs/CI_CD_SESSION_2_HANDOFF.md).
