# CampusDesk — Development Setup Guide

## Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.2+ | Required by Laravel 12 |
| Composer | 2.x | |
| Node.js | LTS (18+ or 20+) | Compatible with Vite 6 |
| MySQL | via XAMPP | |
| XAMPP | Any recent version | Provides PHP, MySQL, Apache/phpMyAdmin on Windows |

**Confirmed environment:** Windows + XAMPP + VS Code + MySQL via phpMyAdmin.

## Repository Structure

```
CampusDesk/
├── campusdesk/         ← Laravel backend (PHP)
│   ├── app/
│   ├── database/
│   │   ├── migrations/      ← 27 migration files
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

**⚠️ Important:** `Frontend/app/` is an abandoned Next.js scaffold — ignore it. All Vue development is in `Frontend/src/`.

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
| "Session store not set on request" | Logout endpoint calls session methods in API context | Known bug — see KNOWN_ISSUES.md |
| Field silently null after create/update | Missing `$fillable` entry | Check the model's `$fillable` array first |
| 401 on every authenticated request | Token not attached, or Axios `Authorization` header issue | Check `api.ts` interceptor |
| Route model binding passes a string instead of model | Route `{param}` name doesn't match controller argument name | Rename to match exactly |
| File upload arrives as `{}` | Axios instance has a default `Content-Type: application/json` overriding multipart | Remove default Content-Type from Axios instance |
| 403 on attachment view | File stored in private storage but treated as public URL | Confirm using `AttachmentController`, not raw storage path |
| Seeder fails with RuntimeException about missing dept code | `RequestTypeSeeder` cannot find `TRD`/`AOE`/`AOC` departments | Run `DepartmentSeeder` first; confirm it completed without errors |
| Staff login fails 403 | `dept_admin` users hit the `is_dept-admin` gate bug | Known active bug in `AppServiceProvider` — see KNOWN_ISSUES.md |

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
