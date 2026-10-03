# Issue #7 frontend implementation and verification

Branch: `development-7`. Implementation dates: 2?3 October 2026.

## Scope and acceptance mapping

The full [GitHub issue](https://github.com/Sama-Mokom/CampusDesk/issues/7) was retrieved through the GitHub API. It is open, has no comments, and carries `enhancement`, `frontend`, and `future-phase` labels. Its nine linked mockups are consistent with the individual local references. The source review covered router guards, singleton auth, Axios interceptors, all services and role dashboards, attachment authorization, stage transitions, backend resources/validators, Tailwind configuration and existing tests.

- **Design system and layout refactor ? implemented.** Slate/sky tokens, native form controls styled through shared classes, common feedback/skeleton/empty components, accessible dialogs, role-aware sidebar/header/mobile navigation, and authentication layout.
- **Student portal ? implemented and component-tested.** Real identity/metrics, search/status filters, paginated request cards, API-backed creation/uploads and routing confirmation, actual detail timeline/history/documents, reopening and collection. The codebase's existing creation flow is form ? confirmation; no additional wizard steps were invented from mockups.
- **Staff portal ? implemented and component-tested.** Primary-department queue, separate all-department active cases, claim confirmation, request inspection, approval/rejection, required rejection notes, optional approval notes, and backend-controlled forwarding. Queue failures do not hide active cases.
- **Department Admin ? implemented and component-tested.** Real primary-department metrics, claimability/blocked stages, search/availability filters, local pagination, claimed-stage reassignment, details and assignment history.
- **Super Admin ? implemented and component-tested.** Real system metrics, filters/server pagination, request detail/reopen, reference CRUD and symbolic routing, user CRUD/memberships/admin levels, safe-delete feedback and status-history browsing.
- **Polish and interaction ? implemented; browser verification pending.** Responsive layout rules, loading/empty/filtered-empty/error/success states, focus rings, labels, native modal focus management and reduced motion. Component tests exercise behavior; they do not prove browser layout fidelity.
- **Existing service/state/API alignment ? preserved.** No backend, database, endpoint, permission or workflow sequencing changes. No production API mocks.

Issue #7 must remain open until the visual/manual checks below are completed.

## Architecture and deliberate differences

See [UI_UX.md](UI_UX.md) for the design tokens, shared components, per-route crosswalk and responsive rules. The main extracted components are `student/StudentRequestForm`, `student/StudentRequestCard`, `staff/StaffCaseCard`, `admin/AdminUserFields`, `admin/AdminPagination`, `admin/AdminAuditTrail`, and `layout/AuthLayout`.

The mockups' nonfunctional SSO/profile/preference/priority/share/request-changes controls were omitted. No global document library or profile editing API exists. Local request search complements the existing endpoints. Student input validation follows the backend's existing file extensions/size and description constraints.

Three frontend defects were addressed while preserving API contracts:

1. `App.vue` now invokes the existing `/logout` revocation service before clearing local auth. The old shell only cleared localStorage.
2. Admin student editing maps profile fields explicitly, preserving the user ID even when `student_profile.id` differs. The former object spread could target the wrong `/admin/users/{id}` endpoint.
3. Registration programmes now follow the selected department using the API's existing `department_id`; the previous UI filtered only by faculty.

The document viewer now reports failures, supports retry, cleans up object URLs, and ignores stale downloads. The hardcoded operational-status label and unsupported resolved-today calculation were removed.

## Automated verification

The pre-change baseline passed 37 frontend tests, ESLint and TypeScript. Backend regression verification passed `php artisan test`: 58 tests, 373 assertions. Backend source was unchanged throughout this work.

Executed checks:

- `npm ls --depth=0`: passed; installed packages match the declared dependency tree.
- `npm run lint`: passed.
- `npm run typecheck`: passed.
- `npm test -- --maxWorkers=2 --minWorkers=1`: 15 files, **95 tests passed**. The original 37 tests remain covered; the additional checks bring the total to 95.
- `php artisan test` from `campusdesk/`: **58 tests, 373 assertions passed**.
- Pinned Prettier 3.6.2 formatting was applied to changed frontend files, with single quotes, no semicolons, no trailing commas and one Vue attribute per line. No formatter dependency or unrelated source files were added.
- `git diff --check`: passed.

- `npm run build`: passed; 450 modules transformed. Final generated assets: CSS 44.02 kB (7.77 kB gzip), JavaScript 319.34 kB (103.40 kB gzip).
- `npx --offline prettier@3.6.2 --check --single-quote --no-semi --trailing-comma none --single-attribute-per-line` on all changed frontend Vue/TypeScript/CSS files: passed.

The repository emitted a nonfatal warning that its installed Browserslist data is six months old; dependencies and lockfile were left unchanged. During integration, lint briefly encountered a Vite temporary config file while another command removed it; the final lint run passed after that transient race. Windows process restrictions required escalated test/build/preview execution.

The production preview was started with `npm run preview -- --host 127.0.0.1 --port 4173 --strictPort`. HTTP smoke requests to `/login`, `/register`, `/student`, `/staff`, `/dept-admin` and `/admin` each returned 200 with the application mount point. This validates SPA hosting, not rendered route authorization or visual behavior.

The existing Vitest/jsdom stack was extended with coverage for student submission/upload/filter/detail/reopen/collect, staff claim/resolve and department selection, admin CRUD confirmation/identity/reassignment, role redirects/auth persistence/logout, registration, status labels, modal focus/dismissal, and protected document race/error/cleanup behavior.

## Visual and manual verification ? blocked

All 17 individual supplied references were inspected across the implementation team. No runtime screenshots were captured and no target viewport is claimed as visually verified. The session's computer-use inventory returned no apps or browsers; both in-app browser and Chrome creation returned ?Browser is not available.? The user was asked to connect a browser while implementation continued. Browser E2E infrastructure (Playwright/Cypress) is not configured in this repository.

Required follow-up at **375, 768, 1024 and 1440 pixels**:

- Sign in as each role, verify navigation/refresh/logout, and check long names and descriptions without horizontal page overflow.
- Create a student request with an attachment, locate it, inspect the genuine timeline/history, preview/download its file, and exercise reopen/collection on eligible data.
- Claim a staff queue item in the correct department, inspect details, approve/forward or reject, and verify active-case updates.
- Reassign a department stage and exercise Super Admin user/reference/routing forms and request oversight against the real backend.
- Check keyboard focus/return, Escape and scroll containment in dialogs; test notifications and upload errors; compare actual spacing/typography at every target width to the references.
- Check browser PDF rendering and the download fallback, loading skeletons, empty datasets and filtered-empty/error states.

## Existing limitations retained

- Department admins may claim and reassign, but their dashboard has no resolution controls and the `/staff` guard permits only ordinary staff. Claimed department-admin work must be reassigned to ordinary staff for processing. This pre-existing role-flow limitation was not changed by widening permissions.
- The administrative edit/elevation audit remains separate future backend work; the current UI accurately labels request/stage status history.
- If logout cannot reach the backend, local auth is cleared but remote token revocation cannot be guaranteed, matching the existing logout service policy.
- Inter must be locally available; the app otherwise uses system sans-serif. A lightweight SVG replaces the login photograph.
- Real-browser visual, touch, screen-reader and end-to-end workflow verification is pending; automated green checks alone do not establish production readiness.

## Git audit

Work remained on `development-7`. Local implementation commits:

- `5935dc7` — shared design system, application/auth shell, documents, notifications and accessibility tests.
- `72505ab` — student requests and tracking.
- `451f40e` — staff queue and resolution workspace.
- `96bc75d` — department and Super Admin oversight, forms and regression tests.

The final documentation commit records these checks and the remaining acceptance work. No push, merge, deployment or GitHub issue closure was performed. Source changes are confined to `Frontend/` plus related documentation. The pre-existing untracked file `tatus --short` was left untouched. Backend, CI/CD workflows, application dependencies and lockfiles are unchanged.
