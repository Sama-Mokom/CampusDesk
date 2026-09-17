# CampusDesk technology stack

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
| Laravel 12 / PHP 8.2+ | REST API, business logic, queues, and email |
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

Development is currently Windows/XAMPP oriented. Composer scripts support backend
setup, development, and tests; npm scripts in `Frontend/package.json` run Vite,
Vitest, and the production build. No CI/CD, Docker configuration, browser E2E
suite, or deployment target is configured.
