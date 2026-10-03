# CampusDesk UI and component system

Last updated: 3 October 2026. Implemented for [Issue #7](https://github.com/Sama-Mokom/CampusDesk/issues/7) on `development-7`.

## Design authority and functional scope

The individual references in `Mockups/Frontend` and `Mockups/Mobile`, plus `UI Prompt Campus desk.txt`, establish the visual direction. The Laravel endpoints and existing Vue service modules determine functionality. All nine desktop and eight mobile references were inspected individually across the implementation team. The issue was read through the GitHub API, including its labels and empty comments collection.

The implementation uses slate surfaces, sky accents, restrained borders, clear type hierarchy and real workflow information. Illustrative SSO, password-reset links, profile editing, priorities, request changes, document sharing and unimplemented navigation destinations were not added. The login artwork is a lightweight architectural SVG rather than the reference's stock photograph. There is no dark mode or external font request; Inter is preferred when available, with system fonts as the reliable fallback.

## Shared design system

`Frontend/tailwind.config.ts` provides the palette and type stack; `src/style.css` defines semantic surface/brand variables and reusable classes for buttons, cards, fields, metrics, feedback and status pills. Spacing follows the 4/8/16/24/32 rhythm. Primary button backgrounds use the darker sky `#0369A1` for white-text contrast; `#0284C7` remains the brand/focus accent. DaisyUI remains disabled to avoid its theme-variable overrides.

Reusable components live in `src/components/ui`:

- `BaseModal`: native modal dialog, accessible title, focus containment/return, background inertness, Escape/backdrop dismissal, busy guards, body scroll lock and scrollable content. Footer actions use native form ownership where needed.
- `PageHeader`: one page heading, contextual eyebrow and actions.
- `EmptyState` and `SkeletonLoader`: reusable asynchronous-state presentation.
- `AppIcon`: one small set of consistent stroke icons.

Native form controls retain labels, autocomplete, validation semantics and keyboard behavior. Shared CSS establishes their appearance instead of wrapping every input in a component. Domain components handle meaningful repeated UI: `student/StudentRequestCard`, `student/StudentRequestForm`, `staff/StaffCaseCard`, and admin pagination, user fields and audit history.

`StatusBadge` reuses the centralized request/stage labels in `types/index.ts`. Color is accompanied by readable text: draft/slate, pending/amber, in-review/sky, forwarded/purple, ready or approved/green, collected/emerald, rejected/red.

## Application shell and authentication

`App.vue` provides a persistent slate desktop sidebar, role-specific navigation to real routes and section anchors, a sticky header, notifications and logout. Below 1024px the sidebar becomes a keyboard-operable navigation disclosure. A skip link leads to the main content. Decorative links and the former hardcoded operational-status claim were removed.

`components/layout/AuthLayout.vue` supplies the shared split authentication layout. Login has pending/error states and an accessible password-visibility control. Registration loads reference data with retry and cascades faculty to department to programme; the Programme type now reflects the API's existing `department_id` field.

Sanctum Bearer authentication, localStorage persistence and role guards are retained. Logout now calls the already-existing revocation service before clearing the local session; local logout remains available if the request fails. No backend or permission rules were changed.

## Routes and workflow crosswalk

- **Login/registration references -> `/login`, `/register` -> `AuthLayout` and the existing auth/reference services.** Adapted to email/password login and student registration supported by the API.
- **Student overview/request references -> `/student` -> `StudentDashboard`, `StudentRequestCard`, `StudentRequestForm`.** Real counts, academic context, local search/status filters and six-card pagination. Submission retains the existing form-to-confirmation flow, then shows returned department routing. Type IDs are selected from the API; uploads retain multipart handling and PDF/DOCX/JPG/PNG, 5 MB validation. Failed submission retains input; pending actions prevent duplicates.
- **Student detail/document references -> shared detail dialog, `RequestTimeline`, `DocumentViewer`.** Actual stage order, active step, notes, dates, history, attachments, reopen and collection are preserved. Request details are fetched through the existing service.
- **Staff design direction -> `/staff` -> `StaffDashboard`, `StaffCaseCard`.** Primary-department queue selection and all-department active cases remain distinct. Search/type filters, claim confirmation, real detail loading and resolution use the original request/stage identifiers. Approval notes remain optional, rejection notes required. Approval forwarding is backend-controlled; no new actions were fabricated. Assigned department count replaces the unsupported resolved-today metric.
- **Department administration -> `/dept-admin` -> `DeptAdminDashboard`.** Primary-department metrics, claimability, blocked stages, staff assignments and reassignment history remain API-backed. Search/filter/pagination and assignment dialogs preserve claim and reassignment contracts.
- **Super administration -> `/admin` -> `AdminDashboard`, `admin/*`.** Preserves system statistics, server-paginated request/user/reference/history collections, rejected-request reopen, CRUD, symbolic routing sequences, staff memberships and admin levels. Forms and destructive confirmation use the shared dialog. Stale collection responses are ignored. Status history is explicitly distinguished from an administrative edit audit, which the backend does not provide.

## Documents, timeline and notifications

`DocumentViewer` always fetches `/attachments/{id}` using the authenticated Axios instance. The storage path is never used as a public URL. Images and PDFs render from object URLs; downloads and opening a new tab use the same fetched blob. Failed retrieval retains the selected document and offers retry. Object URLs are revoked on replacement, close and unmount; late responses cannot overwrite a newer selection or reopen a closed preview. PDF controls are provided by the browser, with a download option for browsers without an embedded PDF viewer.

`RequestTimeline` uses an ordered list sorted by `sequence_order`. Only the first unresolved stage after approved predecessors is current. Rejected stages prevent later stages from being represented as active. Approved/rejected indicators include text, and the current stage has `aria-current="step"`.

Notifications retain authenticated loading and mark-read APIs, with loading/error/retry/empty states, outside-click and Escape dismissal, unread counts, and a mobile panel constrained to the viewport.

## Responsive and accessibility conventions

Target widths are 375, 768, 1024 and 1440 pixels. Components implement these through responsive CSS, but runtime visual verification is still required (see the verification record).

- Student request/form columns use approximately 65/35 above 1280px and stack below, leaving room for the desktop sidebar at 1024px.
- Request and admin collections use cards or stacked rows on narrow screens; filters and actions wrap. Filenames and descriptions wrap within shrinkable containers.
- Dialogs are centered and width-constrained on larger screens, becoming bottom sheets with bounded vertical scrolling below 640px.
- Buttons and form fields generally have a 44px minimum target. All icon controls have accessible names, focus-visible rings remain, and errors/successes use alert/status semantics.
- Route, hover and disclosure transitions are restrained. `prefers-reduced-motion` disables animation and smooth scrolling.
- Native dialogs handle background inertness; component tests verify title association, focus wrap/return, Escape cancellation and pending-action dismissal guards. Screen-reader behavior and real-browser focus still need manual verification.

## Verification and limitations

See [FRONTEND_REDESIGN.md](FRONTEND_REDESIGN.md) for executed checks and acceptance status. API mocks exist only in tests. The production app does not contain demo responses. The existing Vitest/jsdom infrastructure was extended; browser E2E infrastructure is not configured.
