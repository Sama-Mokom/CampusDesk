# CampusDesk — Project Overview

## Project Name
**CampusDesk** — University Document Request & Tracking System

## Problem Being Solved

At Cameroonian universities (and many African universities generally), students who need official documents — transcripts, attestations, recommendation letters, internship endorsements — submit requests through informal channels and then have no visibility into where their document is, who has it, or when it will be ready. Students make repeated physical trips to offices or spend time pestering secretaries. The process is opaque, slow, and frustrating.

CampusDesk digitises this process: students submit requests online, requests flow through the relevant departments in a defined sequence, staff process each stage, and students receive real-time status updates via email.

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
2. **Learn Laravel and PHP backend development** — the project is explicitly a learning vehicle. The developer uses traditional resources (Laravel docs, Stack Overflow) rather than AI for code generation. Claude's role is to guide, review, and mentor — not to generate code.

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

## Long-term Vision (planned, not built)

- Department admin oversight and stage reassignment
- Super admin system management (CRUD for all entities)
- Notification bell/dropdown in UI
- Request reopen flow (currently stubbed)
- Mark as collected flow (currently stubbed)
- Analytics dashboard
- Potential adoption by University of Buea or GYGM Foundation

## Major Constraints

- **Windows + XAMPP** development environment (not Linux/Mac)
- **Learning project** — code is hand-written by the developer against documentation, not AI-generated
- **No cloud deployment** — runs locally during development
- **Single developer** — no team coordination required

## Project Origin

Suggested by Claude as a non-generic project that solves a real problem the developer personally experiences. Chosen over a todo-app or blog because it naturally covers all Laravel essentials: Eloquent ORM, migrations, relationships, middleware, queues, mail, storage, policies, and RESTful API design.
