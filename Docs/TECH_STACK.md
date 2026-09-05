# CampusDesk — Technology Stack

## Backend

| Technology | Version | Purpose | Status |
|---|---|---|---|
| PHP | 8.2 | Runtime | ✅ Implemented |
| Laravel | 12.x | Backend framework | ✅ Implemented |
| Laravel Breeze | 2.x (API mode) | Auth scaffolding | ✅ Implemented |
| Laravel Sanctum | 4.x | Token-based API authentication | ✅ Implemented (Bearer token mode) |
| MySQL | via XAMPP | Primary database | ✅ Implemented |
| Laravel Queue (database driver) | built-in | Async job processing | ✅ Implemented |
| Mailtrap | N/A (SaaS) | Development email testing | ✅ Implemented |
| PHPUnit | 11.x | Backend testing | ✅ Installed; 2 feature test files written |

**Note on framework version:** The project uses Laravel **12.x** (as declared in `composer.json` `"laravel/framework": "^12.0"`). Earlier documentation incorrectly stated Laravel 11.

**Why Laravel:** Explicitly chosen as the learning target — the entire project exists to build Laravel proficiency. Not evaluated against alternatives; the framework choice was the starting premise, not a decision made during the project.

**Why Sanctum Bearer tokens over cookie/SPA mode:** See DECISIONS.md ADR-01.

**Why database queue driver (not Redis):** Simplest option requiring no additional infrastructure beyond what XAMPP already provides.

## Frontend

| Technology | Version | Purpose | Status |
|---|---|---|---|
| Vue | ^3.4 | Frontend framework | ✅ Implemented |
| Vite | ^6.0 | Build tool / dev server | ✅ Implemented |
| TypeScript | 5.7.3 | Type safety | ✅ Implemented (strict mode) |
| Vue Router | ^4.6 | Client-side routing | ✅ Implemented |
| Axios | ^1.18 | HTTP client | ✅ Implemented |
| Tailwind CSS | ^3.4 | Utility-first styling | ✅ Implemented |
| DaisyUI | ^5.5 | Tailwind component library | ✅ Implemented |
| date-fns | ^3.0 | Date formatting | ✅ Implemented |
| Vitest | ^2.1 | Frontend unit testing | ✅ Installed; 4 test files written |
| @vue/test-utils | ^2.4 | Vue component testing | ✅ Installed (used by Vitest tests) |
| zod | ^3.24 | Schema validation | ⚠️ Installed but UNUSED in `src/` |

**Not installed:** Pinia, Vuex, ofetch, ky.

**Why Vue 3 + Vite:** The frontend was originally scaffolded using v0 (Vercel's AI UI generator) which produces this stack by default. Not a deliberate choice made through the mentoring conversation — inherited from the initial scaffolding tool.

**Why no state management library (Pinia/Vuex):** See DECISIONS.md ADR-09 — the pre-existing `useMockData.ts` composable pattern was extended rather than introducing a new dependency.

**Why Axios over native Fetch:** Axios's interceptor API made implementing the Bearer-token-attachment and 401-handling patterns significantly more ergonomic than manually wrapping `fetch()` calls.

## Development Environment

| Tool | Purpose |
|---|---|
| XAMPP | Local PHP/MySQL/Apache stack (Windows) |
| VS Code | Primary code editor |
| Postman | API testing during backend-only development |
| phpMyAdmin | Database inspection/management (bundled with XAMPP) |
| `php artisan tinker` | Interactive debugging / manual data manipulation |
| Cursor Agent | Used once, to refactor the v0-scaffolded frontend to align with the finalized backend data model |

## Frontend Scaffolding Tools (historical, not part of ongoing dev)

- **v0 (Vercel)** — generated the initial multi-role dashboard UI from a detailed prompt
- **Cursor Agent** — given a comprehensive prompt to align the v0-generated mock UI's data shapes, navigation, and role logic with the finalized Laravel backend model, before real API wiring began

## Convenience Scripts

The backend `composer.json` defines useful scripts:

| Command | What it does |
|---------|-------------|
| `composer run dev` | Starts `php artisan serve` + `php artisan queue:listen` + `php artisan pail` + `npm run dev` concurrently via `npx concurrently` |
| `composer run test` | Runs `php artisan config:clear` then `php artisan test` |
| `composer run setup` | Installs deps, copies `.env`, generates key, migrates, installs npm deps, builds frontend |

The frontend `package.json` defines:

| Command | What it does |
|---------|-------------|
| `npm run dev` | Starts Vite dev server |
| `npm run build` | Production build |
| `npm test` | Runs Vitest in run (non-watch) mode with verbose output |

## Explicitly NOT Used

- No CI/CD pipeline
- No containerization (Docker) — runs directly on XAMPP
- No deployment platform configured — local development only
- No Pinia/Vuex — singleton composable pattern used instead
- No Pest (PHPUnit is used directly)
- No Cypress/Playwright — only Vitest unit tests for frontend

## Abandoned / Leftover Files

The `Frontend/` directory contains remnants of an abandoned Next.js scaffold:

| Path | Status |
|------|--------|
| `Frontend/app/` | ❌ Abandoned Next.js `app/` directory — ignore |
| `Frontend/next.config.mjs` | ❌ Abandoned Next.js config — ignore |
| `Frontend/components/` | ❌ shadcn/ui component stubs — not used by the Vue app |
| `Frontend/hooks/` | ❌ Next.js-style React hooks — not used by the Vue app |

The actual working Vue SPA is entirely within `Frontend/src/`.
