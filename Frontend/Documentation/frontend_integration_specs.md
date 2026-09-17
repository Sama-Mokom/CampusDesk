# CampusDesk frontend integration reference

## Current implementation

`Frontend/` is a single Vue 3 + Vite SPA. The application entry point is
`src/main.ts`; all production UI, routing, state, types, and API integrations
live under `src/`. There is no Next.js application, React component library, or
in-memory mock-data layer in the repository.

The frontend calls the Laravel API in `../campusdesk/routes/api.php` through
Axios. Set `VITE_API_URL` to the API base URL, normally
`http://127.0.0.1:8000/api` for local development.

## Transport and authentication

- `src/services/api.ts` creates the shared Axios client.
- A request interceptor sends the Sanctum Bearer token stored as `token` in
  `localStorage`.
- `src/composables/useAuth.ts` persists the authenticated user as `user` in
  `localStorage` and restores it on application load.
- A `401` response clears the stored session and redirects an authenticated
  page to `/login`.
- Login, registration, and logout calls are implemented in `src/services/auth.ts`.

The backend permits the local frontend origin through `FRONTEND_URL` (default
`http://localhost:5173`). Although Sanctum's stateful-domain setting remains
available, this SPA uses Bearer tokens rather than cookie authentication.

## Application modules

| Area | Frontend module | API area |
|---|---|---|
| Authentication | `services/auth.ts`, `composables/useAuth.ts` | `/api/register`, `/api/login`, `/api/logout` |
| Student requests | `services/requests.ts`, `StudentDashboard.vue` | `/api/requests` |
| Staff stages | `services/stages.ts`, `StaffDashboard.vue` | `/api/stages`, request-stage actions |
| Department administration | `services/deptAdmin.ts`, `DeptAdminDashboard.vue` | `/api/dept-admin` |
| Super administration | `services/admin.ts`, `AdminDashboard.vue` | `/api/admin` |
| Reference data | `services/reference.ts` | public reference endpoints |
| Notifications | `services/notifications.ts`, `NotificationBell.vue` | `/api/notifications` |

`DocumentViewer.vue` retrieves attachments as authenticated blobs from
`/api/attachments/{attachment}`; it does not use public storage URLs.

## Routing

`src/router/index.ts` protects authenticated routes and routes users by role:
students use `/student`; ordinary staff use `/staff`; department admins use
`/dept-admin`; and super admins use `/admin`. Route guards use the persisted
auth state and enforce staff admin-level requirements.

## Local commands

```bash
cd Frontend
npm install
# .env: VITE_API_URL=http://127.0.0.1:8000/api
npm run dev
npm run test
npm run build
```

For the endpoint payloads, authorization rules, and status transitions, use
the maintained [API reference](../../Docs/API.md) and
[user-flow documentation](../../Docs/USER_FLOWS.md).
