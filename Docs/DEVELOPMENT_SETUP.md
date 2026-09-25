# CampusDesk — Development Setup Guide

## Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.2+; Docker image uses 8.3 | Required by Laravel 12 |
| Composer | 2.x | |
| Node.js | LTS; Docker build uses Node 22 | Compatible with Vite 6 |
| MySQL | Docker MySQL 8.4 or an existing local server | |
| Docker Desktop | Current Linux-container release | Required for the verified Compose workflow |
| XAMPP | Any recent version | Provides PHP, MySQL, Apache/phpMyAdmin on Windows |

**Confirmed environments:** Windows bare development with local PHP/MySQL, plus Docker Desktop using Linux containers for the reproducible Compose stack.

## Repository Structure

```
CampusDesk/
├── campusdesk/         ← Laravel backend (PHP)
│   ├── app/
│   ├── database/
│   │   ├── migrations/      ← 28 migration files
│   │   ├── seeders/         ← automated seeder suite
│   │   │   └── support/     ← parsers, mappers, university data markdown
│   │   └── factories/
│   ├── routes/
│   ├── config/
│   ├── bootstrap/
│   ├── resources/views/emails/
│   ├── tests/Feature/       ← PHPUnit feature tests
│   └── .env
├── Frontend/            ← Vue 3 SPA (TypeScript)
│   ├── src/             ← ALL active Vue code lives here
│   │   ├── components/
│   │   ├── views/
│   │   ├── services/
│   │   ├── composables/
│   │   ├── router/
│   │   └── types/
│   ├── package.json
│   ├── vite.config.js
│   └── .env
└── Docs/               ← This documentation
```

All frontend application code is in `Frontend/src/`; `Frontend/` contains one Vue/Vite project.

## Verified Docker Compose Setup

The preferred reproducible local environment is the four-service Docker Compose stack completed on 25 September 2026. It runs:

- Vue production assets on Nginx at `http://localhost:8080`;
- Laravel on PHP 8.3 and Apache, reachable internally as `backend:80`;
- a separate Laravel database-queue worker;
- MySQL 8.4 with a persistent named volume;
- private attachments in the ignored host directory `docker-data/attachments`.

Create the runtime file from `.env.docker.example`, replace its placeholder passwords, and add a generated `APP_KEY`. Then, on a new database:

```cmd
docker compose --env-file .env.docker build backend frontend
docker compose --env-file .env.docker up -d db backend
docker compose --env-file .env.docker exec backend php artisan migrate
docker compose --env-file .env.docker up -d worker frontend
```

For routine use:

```cmd
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs -f
docker compose --env-file .env.docker down
```

Do not use `docker compose down -v` unless the MySQL data is intentionally being deleted. See [CI_CD_SESSION_1_DOCKER.md](CI_CD_SESSION_1_DOCKER.md) for the architecture, full setup, rebuild procedure, verification record, and troubleshooting history.

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
```

Create the `campusdesk` database in phpMyAdmin (or via MySQL CLI), then:

```bash
# Run migrations + full automated seeder suite
php artisan migrate:fresh --seed
```

This seeds: all UB faculties, departments, programmes, 80 staff users, students (~10 per eligible academic department), department-staff assignments, four request types, 24 lifecycle-varied requests, attachment fixtures, and in-app notification fixtures. Email is never sent by the seeders. See [IMPLEMENTATION_UPDATES.md](IMPLEMENTATION_UPDATES.md) for lifecycle and data-integrity details.

```bash
# One-command startup (server + queue worker + log viewer together)
composer run dev
```

Or run in separate terminals:

```bash
# Terminal 1: dev server
php artisan serve
# → http://127.0.0.1:8000

# Terminal 2: queue worker (required for email notifications)
php artisan queue:work
```

## Creating Manual Staff Test Users (Tinker)

Public HTTP registration intentionally creates students only. For local manual testing, use the comprehensive [Staff Tinker guide](TINKER_STAFF_USERS.md). It covers plain staff, department admins, super admins, any department assignment, multi-department membership, verification, and cleanup.

## Frontend Setup

```bash
cd Frontend

npm install

# Create .env
echo "VITE_API_URL=http://127.0.0.1:8000/api" > .env

npm run dev
# → http://localhost:5173
```

## Running Tests

```bash
# Backend (PHPUnit)
cd campusdesk
php artisan test
# or
composer run test

# Frontend (Vitest)
cd Frontend
npm test
```

## Backend `.env` Reference (Key Values)

```env
APP_NAME=CampusDesk
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=campusdesk
DB_USERNAME=root
DB_PASSWORD=

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
```

## Frontend `.env` Reference

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

## Common Development Commands

| Command | Purpose |
|---------|---------|
| `composer run dev-mail` | Start server + queue + logs + vite all in one |
`composer run dev` | Start server + logs + vite all in one. To avoid overpopulating mailtrap in development|
| `php artisan serve` | Start Laravel dev server only |
| `php artisan queue:work` | Process queued jobs (required for emails) |
| `php artisan migrate:fresh --seed` | Reset database completely and reseed all data |
| `php artisan db:seed --class=Database\\Seeders\\RequestTypeSeeder` | Rerun request-type reference data after departments are seeded |
| `php artisan route:list` | Verify registered routes and middleware |
| `php artisan tinker` | Interactive REPL for manual data manipulation |
| `php artisan test` | Run PHPUnit tests |
| `npm run dev` | Start Vite dev server (frontend) |
| `npm test` | Run Vitest tests (frontend) |
| `npm run build` | Production build of frontend |

## Common Problems & Troubleshooting

See KNOWN_ISSUES.md for the full bug history. Quick reference for common setup issues:

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| CORS error in browser console | `FRONTEND_URL`/`SANCTUM_STATEFUL_DOMAINS` mismatch, or `HandleCors` not prepended | Check `config/cors.php`, `bootstrap/app.php` |
| "Session store not set on request" | Historical logout bug in session-based handler | Current logout uses Sanctum token revocation; confirm the running backend is up to date |
| Field silently null after create/update | Missing `$fillable` entry | Check the model's `$fillable` array first |
| 401 on every authenticated request | Token not attached, or Axios `Authorization` header issue | Check `api.ts` interceptor |
| Route model binding passes a string instead of model | Route `{param}` name doesn't match controller argument name | Rename to match exactly |
| File upload arrives as `{}` | Axios instance has a default `Content-Type: application/json` overriding multipart | Remove default Content-Type from Axios instance |
| 403 on attachment view | File stored in private storage but treated as public URL | Confirm using `AttachmentController`, not raw storage path |
| Seeder fails with RuntimeException about missing dept code | `RequestTypeSeeder` cannot find `TRD`/`AOE`/`AOC` departments | Run `DepartmentSeeder` first; confirm it completed without errors |
| Department admin route returns 403 | User lacks `dept_admin` level or the required primary department | Check `staff_profiles.admin_level` and `department_staff.is_primary`; the gate-name bug is resolved |

## Testing the API Manually (Postman)

A Postman collection was used during backend development. No exported `.json` file has been found in the repository. To recreate, use this sequence:

1. `POST /api/register` — create a student
2. `POST /api/login` — get a Bearer token (also works for staff created via Tinker)
3. `GET /api/requests` — verify auth works
4. `POST /api/requests` — submit a request
5. `GET /api/requests/{id}` — verify detail response
6. (as staff) `GET /api/stages` — verify queue
7. `POST /api/requests/{id}/stages/{id}/claim`
8. `PATCH /api/requests/{id}/stages/{id}/resolve`

Set Postman environment variables `base_url=http://127.0.0.1:8000/api` and `token=` (populated after login) to avoid retyping.
