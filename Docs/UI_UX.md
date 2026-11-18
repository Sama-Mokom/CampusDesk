# CampusDesk UI and component system

Last updated: 18 November 2026. Implemented for [Issue #7](https://github.com/Sama-Mokom/CampusDesk/issues/7), originally developed on `development-7` and now merged into `development`. The dark-theme and notification-inbox follow-up is recorded in [THEME_REFINEMENT.md](THEME_REFINEMENT.md).

## Design authority and functional scope

The individual references in `Mockups/Frontend` and `Mockups/Mobile`, plus `UI Prompt Campus desk.txt`, establish the visual direction. The Laravel endpoints and existing Vue service modules determine functionality. All nine desktop and eight mobile references were inspected individually across the implementation team. The issue was read through the GitHub API, including its labels and empty comments collection.

The implementation uses slate surfaces, sky accents, restrained borders, clear type hierarchy and real workflow information. Illustrative SSO, password-reset links, profile editing, priorities, request changes, document sharing and unimplemented navigation destinations were not added. The login artwork is a lightweight architectural SVG rather than the reference's stock photograph. Inter is preferred when available, with system fonts as the reliable fallback; no external font request is made.

Both light and dark themes now share one semantic token set. The app uses the existing `.dark` class on `<html>`, applies the stored/system preference before first paint, and exposes a single Light/Dark/System control in every header. The sidebar and authentication masthead intentionally remain fixed dark “inverse” chrome in both themes.

## Shared design system

`Frontend/src/style.css` defines the semantic surface, border, text, accent, status and inverse variables for both themes; `Frontend/tailwind.config.js` maps them into utilities (`bg-surface-card`, `text-muted`, `border-subtle`, `bg-status-warning-bg`, and so on). `Frontend/tailwind.config.ts` re-exports that JS config so the two cannot drift. Components consume tokens rather than raw palette utilities. Spacing follows the 4/8/16/24/32 rhythm. Primary button backgrounds use the darker sky `#0369A1` for white-text contrast; `#0284C7` remains the brand/focus accent in light mode, with `#38bdf8` for accent text on dark surfaces. DaisyUI remains disabled to avoid its theme-variable overrides.

Reusable components live in `src/components/ui`:

- `BaseModal`: native modal dialog, accessible title, focus containment/return, background inertness, Escape/backdrop dismissal, busy guards, body scroll lock and scrollable content. Footer actions use native form ownership where needed.
- `PageHeader`: one page heading, contextual eyebrow and actions.
- `EmptyState` and `SkeletonLoader`: reusable asynchronous-state presentation.
- `AppBadge`: one tinted dot-and-label badge with `neutral`, `success`, `warning`, `info`, `purple`, `danger` and `orange` tones.
- `AppCallout`: tinted informational/success/warning/danger callout.
- `ThemeToggle`: the single Light/Dark/System menu, with persisted preference and accessible menu semantics.
- `AppIcon`: one small set of consistent stroke icons.

Native form controls retain labels, autocomplete, validation semantics and keyboard behavior. Shared CSS establishes their appearance instead of wrapping every input in a component. Domain components handle meaningful repeated UI: `student/StudentRequestCard`, `student/StudentRequestForm`, `staff/StaffCaseCard`, and admin pagination, user fields and audit history.

`StatusBadge` composes the shared `AppBadge` and reuses the centralized request/stage labels in `types/index.ts`. Colour is accompanied by a dot and readable text: draft/neutral, pending or awaiting payment/amber, in-review/sky, forwarded/purple, ready or approved/green, collected/green, rejected/red. Fee tiers use the same component: Standard neutral, Fast and Super Fast purple.

## Application shell and authentication

`App.vue` provides a persistent slate desktop sidebar, role-specific navigation to distinct pages, a sticky header, one `ThemeToggle`, notifications and logout. The sidebar and header use inverse/semantic tokens respectively, and their 1px borders meet at the same coordinate so the shell reads as one frame. Below 1024px the sidebar becomes a keyboard-operable navigation disclosure. Both menus mark the current destination, including student request details, and scroll within the viewport when needed. Navigation moves keyboard focus to the main content; the main landmark itself suppresses the visible focus ring so it does not paint a box around the whole content area. The accessibility skip link remains the only shell hash link.

`components/layout/AuthLayout.vue` supplies the shared split authentication layout. Login has pending/error states and an accessible password-visibility control. Registration loads reference data with retry and cascades faculty to department to programme; the Programme type now reflects the API's existing `department_id` field.

Sanctum Bearer authentication, localStorage persistence and role guards are retained. Logout now calls the already-existing revocation service before clearing the local session; local logout remains available if the request fails. No backend or permission rules were changed.

## Routes and workflow crosswalk

- **Login/registration references -> `/login`, `/register` -> `AuthLayout` and the existing auth/reference services.** Adapted to email/password login and student registration supported by the API.
- **Student overview -> `/student`.** Academic context, genuine metrics and recent request links in `views/student/StudentOverviewPage.vue`.
- **Student request list -> `/student/requests`.** `StudentRequestsPage` owns search, status filters and six-card pagination. `StudentRequestCard` remains reusable.
- **Student submission -> `/student/requests/new`.** `StudentNewRequestPage` loads request types and embeds `StudentRequestForm`. Submission retains the form-to-confirmation flow and returned department routing. Uploads keep multipart handling and PDF/DOCX/JPG/PNG, 5 MB validation; failed submissions retain input and pending actions prevent duplicates.
- **Student request details -> `/student/requests/:id`.** `StudentRequestDetailPage` directly fetches the requested ID, including on refresh. It presents actual stages, notes, dates, history, protected attachments, reopening and collection through `RequestTimeline` and `DocumentViewer`, with unavailable/retry states and a return link.
- **Staff overview -> `/staff`.** `StaffOverviewPage` shows primary-department and assigned-case metrics, profile context and links to the two work pages.
- **Staff work -> `/staff/queue` and `/staff/cases`.** `StaffQueuePage` and `StaffCasesPage` share `StaffCaseWorkspace` and `StaffCaseCard`. The queue loads eligible stages and supports department selection; active cases load their own endpoint across all assigned departments. Claiming navigates to active cases. Search/type filters and contextual claim/detail/resolution dialogs preserve the original IDs and workflow. Approval notes remain optional, rejection notes required, and forwarding remains backend-controlled.
- **Department administration -> `/dept-admin` and `/dept-admin/requests`.** Separate `DeptAdminOverviewPage` and `DeptAdminRequestsPage` isolate summary metrics from search/filter/pagination, stage inspection, claims and reassignment. Both use the existing primary-department API.
- **Super Admin overview -> `/admin`.** `AdminOverviewPage` loads statistics and recent activity only.
- **Super Admin management -> `/admin/requests`, `/admin/users`, `/admin/faculties`, `/admin/departments`, `/admin/programmes`, `/admin/request-types`, `/admin/history`.** Dedicated requests, users, references and history pages use page-scoped composables under `composables/admin`. Reference pages share one component configured by the route. Server pagination, filters, request reopening, CRUD, symbolic routing, memberships and admin levels are preserved. Contextual forms and destructive confirmations remain dialogs on the owning page. Status history remains distinct from an administrative edit audit, which the backend does not provide.

The four role views are nested `RouterView` hosts rather than dashboard monoliths. Existing role guards live on the parent routes and apply to every child, including direct URLs. Child components and their data load on demand; overview pages do not render management forms or full work lists. Component-scoped state resets when leaving a page, and role hosts are keyed by the signed-in user. Browser Back/Forward works through Vue Router; history navigation restores the saved scroll position. Forms and filters reset when their page is remounted. The former `*Dashboard.vue` components have been removed.

## Documents, timeline and notifications

`DocumentViewer` always fetches `/attachments/{id}` using the authenticated Axios instance. The storage path is never used as a public URL. Images and PDFs render from object URLs; downloads and opening a new tab use the same fetched blob. Failed retrieval retains the selected document and offers retry. Object URLs are revoked on replacement, close and unmount; late responses cannot overwrite a newer selection or reopen a closed preview. PDF controls are provided by the browser, with a download option for browsers without an embedded PDF viewer.

`RequestTimeline` uses an ordered list sorted by `sequence_order`. Only the first unresolved stage after approved predecessors is current. Rejected stages prevent later stages from being represented as active. Approved/rejected indicators include text, and the current stage has `aria-current="step"`.

Notifications retain authenticated loading and mark-read APIs, with loading/error/retry/empty states, outside-click and Escape dismissal, unread counts, and a mobile panel constrained to the viewport. Unread rows add an accent left rail, dot, tinted surface, heavier message and explicit “Unread · Mark as read” text; read rows are plain with “Read”. A header action marks all unread notifications read using the existing per-notification endpoint, updating optimistically and reverting only rows whose request failed.

## Responsive and accessibility conventions

Target widths are 375, 768, 1024 and 1440 pixels. Components implement these through responsive CSS, but runtime visual verification is still required (see the verification record).

- Student submission has a dedicated form/help layout that stacks on smaller screens; the request list and detail pages use the available content width independently.
- Request and admin collections use cards or stacked rows on narrow screens; filters and actions wrap. Filenames and descriptions wrap within shrinkable containers.
- Dialogs are centered and width-constrained on larger screens, becoming bottom sheets with bounded vertical scrolling below 640px.
- Buttons and form fields generally have a 44px minimum target. All icon controls have accessible names, focus-visible rings remain, and errors/successes use alert/status semantics.
- Route, hover and disclosure transitions are restrained. `prefers-reduced-motion` disables animation and smooth scrolling.
- Native dialogs handle background inertness; component tests verify title association, focus wrap/return, Escape cancellation and pending-action dismissal guards. Screen-reader behavior and real-browser focus still need manual verification.

## Verification and limitations

See [THEME_REFINEMENT.md](THEME_REFINEMENT.md) for the dark-theme and notification verification record. In summary: type-check, lint, the frontend build and 209 Vitest tests pass; the contrast matrix reports 0 failures; axe reports 0 WCAG A/AA violations on the audited screens; and screens were rendered in dark and light at desktop and mobile widths against a temporary fixture API. The production app does not contain demo responses. Native OS date/select popups, real-backend data, screen-reader behavior and forced-colors/high-contrast rendering still require manual acceptance. The existing Vitest/jsdom infrastructure was extended; browser E2E infrastructure is not configured.
