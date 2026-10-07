# CampusDesk — Project Overview

**Last reviewed:** 7 October 2026

## Project Name
**CampusDesk** — University Document Request & Tracking System

## Problem Being Solved

At Cameroonian universities (and many African universities generally), students who need official documents — transcripts, attestations, recommendation letters, internship endorsements — submit requests through informal channels and then have no visibility into where their document is, who has it, or when it will be ready. Students make repeated physical trips to offices or spend time pestering secretaries. The process is opaque, slow, and frustrating.

CampusDesk digitises this process: students submit requests online, requests flow through the relevant departments in a defined sequence, staff process each stage, and students receive in-app updates plus queued email notifications.

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

This project has two explicit goals:

1. **Build a real, useful system** — CampusDesk solves a problem the developer has personally experienced at the University of Buea
2. **Learn Laravel and PHP backend development** — the project is explicitly a learning vehicle. Every assisted change must remain understandable, reviewable, tested, and supported by the framework documentation; automation does not replace the developer's ownership of the design.

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
- Potential adoption by University of Buea

## Major Constraints

- **Windows-first local development** — bare XAMPP-style development remains supported, with Docker Compose as the reproducible local runtime
- **Learning project** — implementation and operations must remain explainable and reviewable by the developer, regardless of which tools assist the work
- **Private single-host staging** — AWS staging is deployed but deliberately reachable only through an SSH tunnel; public DNS/HTTPS is not configured
- **Single developer** — operational reviews and approvals still need explicit records even without a development team

## Project Origin

Suggested by Claude as a non-generic project that solves a real problem the developer personally experiences. Chosen over a todo-app or blog because it naturally covers all Laravel essentials: Eloquent ORM, migrations, relationships, middleware, queues, mail, storage, policies, and RESTful API design.
