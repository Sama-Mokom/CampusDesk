# CampusDesk technology stack

**Last reviewed:** 7 October 2026

## Frontend

| Technology | Use |
| --- | --- |
| Vue 3 | Single-page application and component system |
| TypeScript | Strictly typed frontend code |
| Vite 6 | Development server and production build |
| Vue Router 4 | Client-side role-aware routes |
| Axios | Laravel API client and Bearer-token interceptors |
| Tailwind CSS 3 | Utility styling |
| Vitest + Vue Test Utils | Frontend component tests |

The only frontend application is the Vue/Vite project in `Frontend/`. Its
runtime code is under `Frontend/src/`; there is no Next.js, React/shadcn, or
mock-data application in the repository.

## Backend

| Technology | Use |
| --- | --- |
| Laravel 12 / PHP 8.3 container runtime | REST API, business logic, queues, and email |
| MySQL | Relational application database |
| Eloquent | ORM, relationships, observers, and factories |
| Laravel Sanctum | Bearer-token authentication |
| PHPUnit | Backend feature and unit tests |
| Laravel Storage | Private attachment storage and authenticated streaming |
| Database queue driver | Asynchronous notification email dispatch |

## Application conventions

- The frontend stores the current user and Bearer token in `localStorage`.
- API traffic is configured by `VITE_API_URL`; local development normally uses
  `http://127.0.0.1:8000/api`.
- The backend permits the frontend origin through `FRONTEND_URL`, which defaults
  to `http://localhost:5173`.
- There is no Pinia or Vuex store. `useAuth.ts` owns the small shared auth state.
- `zod` is installed but is not currently imported by production frontend code.

## Local tooling

Development supports both the existing Windows bare-development workflow and a verified Docker Desktop workflow. Docker Compose runs MySQL 8.4, Laravel on PHP 8.3 with Apache, a separate Laravel queue worker, and the compiled Vue application on Nginx. See the consolidated [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md).

Composer scripts support backend setup, development, and tests. npm scripts in `Frontend/package.json` run Vite, ESLint, Vitest, TypeScript checking, and the production build. Browser E2E coverage is not installed.

GitHub Actions runs the backend/frontend quality gates, validates Compose, builds images, and publishes immutable commit-SHA images to Amazon ECR through OIDC. A separate manual-triggered workflow uses a protected `staging` environment, an environment-scoped OIDC role, and a restricted Systems Manager document to deploy exact digests to EC2. The current staging runtime is one Ubuntu 24.04 AMD64 host running Docker Compose. Encrypted, versioned S3 recovery, KMS, SNS, CloudWatch heartbeat monitoring, twice-daily systemd capture, and isolated restore testing are implemented; public DNS/HTTPS and automatic failover are not.

## Delivery and recovery

| Technology | Use |
| --- | --- |
| GitHub Actions | Ubuntu 24.04 quality gates, Compose validation, image builds/publication, and manual staging deployment |
| GitHub OIDC | Short-lived AWS credentials without stored access keys |
| Amazon ECR | Immutable commit-SHA backend/frontend images resolved to digests for deployment |
| AWS Systems Manager | Restricted, approval-gated invocation of the root-owned EC2 deployment script |
| AWS CloudFormation | Recovery bucket, KMS key, alert topic, scoped instance-role policy, and heartbeat alarm |
| Amazon S3 + AWS KMS | Private versioned recovery sets encrypted with the dedicated customer-managed key |
| Amazon SNS + CloudWatch | Encrypted alerts and missing-backup-heartbeat detection |
| systemd timers | Recovery capture at 00:00/12:00 UTC and freshness monitoring every 15 minutes |
