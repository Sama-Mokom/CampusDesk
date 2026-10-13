# CampusDesk — Project Overview

**Last reviewed:** 13 October 2026

## Project Name
**CampusDesk** — University Document Request & Tracking System

## Problem Being Solved

CampusDesk began with a problem experienced firsthand at the University of Buea: after submitting a request for an official document, a student could have little visibility into what happened next. The request might be waiting for review, moving between offices, or blocked because information was missing or incorrect. Without a clear update, the student might only discover the problem after days of waiting or repeated visits to the faculty records office.

That uncertainty has real consequences when a transcript, attestation, recommendation letter, or endorsement is needed for a scholarship, university application, internship, or job opportunity with a deadline. Friends of the project's creator encountered the same pattern, showing that the issue was not an isolated inconvenience but a workflow and communication gap worth solving.

CampusDesk makes that process visible. Students submit requests online, requests move through the responsible offices in a defined sequence, staff process each stage from an organised queue, and students receive in-app and queued email updates when the status changes. A rejection must include a reason, so the student knows what to correct and can reopen the request instead of waiting without direction.

## Target Users

| Role | Description |
|------|-------------|
| **Student** | Submits document requests, tracks progress, receives notifications, collects documents |
| **Staff** | University employees who process request stages in their department |
| **Department Admin** | Senior staff with oversight of their department's queue and ability to reassign cases |
| **Super Admin** | System-wide administrator (registrar / IT officer) with full control |

## Core Value Proposition

- Students get visibility into their document requests without physical trips
- Staff get an organised queue instead of informal piles of requests
- Complete audit trail of every status change
- Multi-department sequential workflows with automatic routing
- Email notifications at every stage transition

## Project Goals

1. **Give students reliable visibility** — make the state, location, outcome, and next required action of each document request clear without repeated office visits.
2. **Give staff a controlled workflow** — replace informal handoffs with ordered departmental queues, concurrency-safe ownership, and explicit approval or rejection decisions.
3. **Make the process accountable** — preserve a history of status changes and staff handoffs so that requests can be followed and operational problems can be investigated.
4. **Prepare for institutional use** — build the security, administration, deployment, recovery, and maintainability needed to evaluate CampusDesk for real university adoption.

## MVP Scope (completed)

- Student registration and authentication
- Document request submission with file attachments
- Multi-department sequential request workflows (with concurrency-safe stage assignment)
- Staff queue, pickup, and stage resolution
- Automatic email notifications on status change
- Complete status history / audit trail
- Student dashboard with request tracking
- Staff dashboard with queue management
- Protected file serving (attachments require authentication)
- Comprehensive automated seeder suite (realistic University of Buea data)
- PHPUnit feature tests for sequential routing concurrency
- Vitest unit tests for key frontend components

## Additional implemented workflows

- Primary-department admin oversight and reassignment of claimed stages
- Super Admin reference and user management, statistics, request oversight, and request/stage status audit
- API-backed notification bell, rejected-request reopen, and collected-request transition
- Shared accessible UI primitives and separate nested task pages for every role
- GitHub Actions quality gates, immutable ECR publication, and approval-gated SSM deployment to private AWS staging
- Encrypted off-host database/attachment recovery, isolated restore testing, and scheduled freshness monitoring

## Long-term vision (planned)

- Separate audit of administrative CRUD and privilege changes
- Broader analytics and real-time event delivery
- Pilot evaluation and potential adoption by the University of Buea

## Major Constraints

- **Windows-first local development** — bare XAMPP-style development remains supported, with Docker Compose as the reproducible local runtime
- **Maintainability and reviewability** — implementation and operational changes must remain explicit, testable, documented, and supportable by a small team
- **Private single-host staging** — AWS staging is deployed but deliberately reachable only through an SSH tunnel; public DNS/HTTPS is not configured
- **Single developer** — operational reviews and approvals still need explicit records even without a development team

## Project Origin

CampusDesk grew from Nkeng Sama Mokom's experience requesting official documents at the University of Buea. After submission, progress was largely invisible: finding out whether a document was ready could require repeated visits, while missing or incorrect information might block a request without the student knowing. The stakes became clearer whenever a document was needed for a time-sensitive scholarship, application, internship, or job opportunity.

The project asks a practical question: **what if students could see where their requests were, what needed attention, and what would happen next?** CampusDesk turns that question into an end-to-end workflow for students, staff, department administrators, and system administrators. The longer account of the problem and product idea is available in [Why I built CampusDesk](https://samamokom.vercel.app/writing/why-i-built-campusdesk).
