# CampusDesk — Development Setup Guide

**Last reviewed:** 21 October 2026

## Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.2+; Docker image uses 8.3 | Required by Laravel 12 |
| Composer | 2.x | |
| Node.js | LTS; Docker build uses Node 22 | Compatible with Vite 6 |
| MySQL | Docker MySQL 8.4 or an existing local server | |
| Docker Desktop | Current Linux-container release | Required for the 7-service Compose workflow |
| XAMPP | Any recent version | Provides PHP, MySQL, Apache/phpMyAdmin on Windows |

**Confirmed environments:** Windows bare development with local PHP/MySQL, plus Docker Desktop using Linux containers for the reproducible Compose stack.

## Repository Structure

```
CampusDesk/
├── campusdesk/         ← Laravel backend (PHP)
│   ├── app/
│   ├── database/
│   │   ├── migrations/      ← 33 migration files
│   │   ├── seeders/         ← automated seeder suite
│   │   │   └── support/     ← parsers, mappers, university data markdown
│   │   └── factories/
│   ├── routes/
│   │   ├── api.php          ← REST API endpoints
│   │   ├── auth.php         ← Authentication routes
│   │   └── channels.php     ← WebSocket channel authorizations
│   ├── config/
│   ├── bootstrap/
│   ├── resources/views/emails/
│   ├── tests/Feature/       ← PHPUnit feature tests (23 suites, 111 tests)
│   └── .env
├── Frontend/            ← Vue 3 SPA (TypeScript)
│   ├── src/             ← ALL active Vue code lives here
│   │   ├── components/
│   │   ├── views/
│   │   ├── services/        ← API clients, Echo setup, realtime subscription
│   │   ├── composables/
│   │   ├── router/
│   │   └── types/
│   ├── tests/           ← Vitest unit tests (18 files, 155 tests)
│   ├── package.json
│   ├── vite.config.js
│   └── .env
└── Docs/               ← This documentation
```

All frontend application code is in `Frontend/src/`; `Frontend/` contains one Vue/Vite project.

## Verified Docker Compose Setup

The reproducible containerized local environment is the seven-service Docker Compose stack expanded in October 2026 to support real-time WebSocket push delivery. It runs:

- `frontend`: Vue production assets served via Nginx at `http://localhost:8080`;
- `backend`: Laravel on PHP 8.3 and Apache, reachable internally as `backend:80`;
- `worker`: Default Laravel database-queue worker processing background email jobs;
- `db`: MySQL 8.4 with a persistent named volume;
- `reverb`: Laravel Reverb WebSocket server daemon listening on port 8080 internally;
- `realtime-worker`: Dedicated queue worker processing the `realtime` queue for fast-path outbox publishing (`PublishRequestEvent`);
- `scheduler`: Background console scheduler executing `realtime:reconcile` every 5 minutes to guarantee at-least-once WebSocket delivery.

Create the runtime file from `.env.docker.example`, replace its placeholder passwords, and add a generated `APP_KEY`. Then, on a new database:

```cmd
docker compose --env-file .env.docker build backend frontend
docker compose --env-file .env.docker up -d db backend
docker compose --env-file .env.docker exec backend php artisan migrate
docker compose --env-file .env.docker up -d
```

For routine use:

```cmd
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f
docker compose --env-file .env.docker down
```

Do not use `docker compose down -v` unless the MySQL data is intentionally being deleted. See the consolidated [CI/CD implementation and operations guide](CI_CD_SESSION_2_HANDOFF.md) for the architecture, full setup, rebuild procedure, verification record, and troubleshooting history.

The bare setup below remains available for fast application development without containers.

## Backend Setup

```bash
cd campusdesk

# Install PHP dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate
```

Edit `.env` — minimum required changes from the example:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=campusdesk
DB_USERNAME=root
DB_PASSWORD=         # blank for default XAMPP MySQL

APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=YOUR_MAILTRAP_USERNAME
MAIL_PASSWORD=YOUR_MAILTRAP_PASSWORD
MAIL_FROM_ADDRESS=noreply@campusdesk.com
MAIL_FROM_NAME="CampusDesk"

# Laravel Reverb (WebSockets)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=campusdesk
REVERB_APP_KEY=campusdesk-key
REVERB_APP_SECRET=campusdesk-secret
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME=http
```

Create the `campusdesk` database in phpMyAdmin (or via MySQL CLI), then:

```bash
# Run migrations + full automated seeder suite
php artisan migrate:fresh --seed
```

This seeds: all UB faculties, departments, programmes, 80 staff users, students (~10 per eligible academic department), department-staff assignments, four request types, 24 lifecycle-varied requests, attachment fixtures, and in-app notification fixtures. Email is never sent by the seeders.

### Starting Local Services

To start the full full-featured stack (HTTP server, queues, Reverb WebSockets, scheduler, and Vite frontend) in a single unified terminal:

```bash
composer run dev-realtime
```

Or run individual components across dedicated terminals:

```bash
# Terminal 1: Laravel HTTP API server
php artisan serve
# → http://127.0.0.1:8000

# Terminal 2: Default queue worker (for email notifications)
php artisan queue:work

# Terminal 3: Dedicated realtime queue worker (for WebSocket outbox publishing)
php artisan queue:work database --queue=realtime

# Terminal 4: Laravel Reverb WebSocket server
php artisan reverb:start --debug
# → ws://127.0.0.1:8080

# Terminal 5: Console scheduler (for realtime:reconcile every 5m)
php artisan schedule:work
```

## Creating Manual Staff Test Users (Tinker)

Public HTTP registration intentionally creates students only. For local manual testing, use the comprehensive [Staff Tinker guide](TINKER_STAFF_USERS.md). It covers plain staff, department admins, super admins, any department assignment, multi-department membership, verification, and cleanup.

## Frontend Setup

```bash
cd Frontend

npm install

# Create .env
cat << 'EOF' > .env
VITE_API_URL=http://127.0.0.1:8000/api
VITE_REVERB_APP_KEY=campusdesk-key
VITE_REVERB_HOST=127.0.0.1
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
EOF

npm run dev
# → http://localhost:5173
```

## Running Tests & Quality Gates

```bash
# Backend (PHPUnit — 111 tests, 592 assertions)
cd campusdesk
php artisan test
# or
composer run test

# Frontend (Vitest — 18 files, 155 tests)
cd Frontend
npm test

# Frontend Linting & Typecheck
npm run lint
npm run typecheck

# Frontend Production Build
npm run build
```

## Common Development Commands

| Command | Purpose |
|---------|---------|
| `composer run dev-realtime` | Start Laravel, default & realtime queues, Reverb, scheduler, and Vite concurrently |
| `composer run dev-mail` | Start Laravel, default queue worker, and Vite together |
| `composer run dev` | Start Laravel and Vite without background queue workers |
| `php artisan serve` | Start Laravel dev server only |
| `php artisan reverb:start` | Start Laravel Reverb WebSocket server |
| `php artisan queue:work --queue=realtime,default` | Process both real-time outbox and email background queues |
| `php artisan schedule:work` | Run local Laravel console scheduler |
| `php artisan realtime:reconcile` | Manually run outbox delivery reconciliation command |
| `php artisan migrate:fresh --seed` | Reset database completely and reseed all reference and demo data |
| `php artisan route:list` | Verify registered API and broadcasting routes |
| `php artisan tinker` | Interactive REPL for manual data manipulation |
| `php artisan test` | Run PHPUnit feature tests |
| `npm run dev` | Start Vite dev server (frontend) |
| `npm test` | Run Vitest tests (frontend) |
| `npm run lint` | Run ESLint check |
| `npm run typecheck` | Run `vue-tsc` type checker |
| `npm run build` | Production build of frontend |

## Common Problems & Troubleshooting

See KNOWN_ISSUES.md for the full bug history. Quick reference for common setup issues:

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| Echo connection fails / WebSockets unavailable | Reverb not running or port 8080 blocked | Start `php artisan reverb:start` and verify `VITE_REVERB_*` match backend `.env` |
| Real-time notifications not arriving | `realtime` queue worker not running | Ensure `queue:work database --queue=realtime` is running |
| CORS error in browser console | `FRONTEND_URL`/`SANCTUM_STATEFUL_DOMAINS` mismatch, or `HandleCors` not prepended | Check `config/cors.php`, `bootstrap/app.php` |
| Account disabled (HTTP 403) | `disabled_at` is set on user record | Super Admin must re-enable account via `PATCH /api/admin/users/{id}/enable` |
| Field silently null after create/update | Missing `$fillable` entry | Check the model's `$fillable` array first |
| 401 on every authenticated request | Token not attached, or Axios `Authorization` header issue | Check `api.ts` interceptor |
| 403 on attachment view | Requester lacks permission or file stored in private storage | Check user ownership, stage assignment, or department route |
