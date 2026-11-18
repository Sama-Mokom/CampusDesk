# CampusDesk — Dark Theme Refinement and Notification Inbox Improvements

**Last reviewed:** 18 November 2026

**Branch:** `fix/theme-ui-followups` (created from `development`)

**Status:** ✅ Implemented and verified on the branch. Not yet merged into `development`.

This is the implementation record for the frontend-only theme consolidation and notification-inbox work completed on 17–18 November 2026. The formal decisions are recorded as [ADR-14, ADR-15 and ADR-16](DECISIONS.md); the current design-system contract is [UI_UX.md](UI_UX.md). No backend, API, database, permission or deployment files were changed.

## Scope and starting point

The repository already contained a partial dark-theme refactor in commit `7815581` (`fix(theme): resolve dark mode defects across all views and shared components`). It had:

- a `.dark` class mechanism and a pre-paint inline script;
- `useTheme.ts` and a single `ThemeSwitcher` control;
- a first set of CSS variables and Tailwind aliases;
- `AppBadge` existed but was not adopted consistently, and `AppCallout` did not exist yet.

It also had **790 raw colour utility occurrences** (785 in 33 application `.vue` files plus 5 class assertions in one test spec), many light-only colours and several theme defects still visible in the application. The work below completed that refactor and corrected the follow-up regressions found during human review.

## Architecture overview

### Semantic token layer

Colour is defined once in `Frontend/src/style.css`:

- `:root` holds the light values;
- `.dark` overrides the same names for dark mode;
- components consume either the CSS variables directly or the Tailwind aliases generated from them.

Tailwind aliases live in `Frontend/tailwind.config.js`. `Frontend/tailwind.config.ts` now re-exports that object so tooling that prefers the TypeScript config cannot drift from the canonical source. The aliases include surfaces, borders, text, accent-soft, inverse chrome, solid danger and the seven tinted status families.

| Token | Light | Dark | Verified minimum |
|---|---|---|---|
| `--surface-page` | `#f8fafc` | `#020617` | layering only |
| `--surface-card` | `#ffffff` | `#0f172a` | layering only |
| `--surface-raised` | `#ffffff` | `#162036` | layering only |
| `--surface-input` | `#ffffff` | `#1e293b` | layering only |
| `--surface-muted` | `#f1f5f9` | `#1e293b` | layering only |
| `--border-subtle` | `#e2e8f0` | `rgba(255,255,255,.10)` | decorative |
| `--border-strong` | `#7b8a9e` | `rgba(255,255,255,.40)` | 3.21 : 1 |
| `--text-heading` | `#0f172a` | `#f8fafc` | 15.56 / 13.26 |
| `--text-body` | `#1e293b` | `#e2e8f0` | 12.75 / 11.26 |
| `--text-muted` | `#586777` | `#94a3b8` | 5.05 / 5.41 |
| `--text-placeholder` | `#586777` | `#94a3b8` | 5.05 / 5.41 |
| `--accent` / `--accent-hover` | `#0369a1` / `#075985` | same | white label 5.93 / 7.56 |
| `--accent-text` | `#0369a1` | `#38bdf8` | 5.17 / 6.48 |
| `--accent-soft-bg` / border / text | `#e0f2fe` / `#7dd3fc` / `#075985` | `#082f49` / `rgba(56,189,248,.30)` / `#7dd3fc` | 6.59 / 8.32 |
| `--overlay` | `rgba(15,23,42,.40)` | `rgba(2,6,23,.70)` | n/a |
| `--status-success-text` | `#047857` | `#6ee7b7` | 4.51 / 7.90 |
| `--status-warning-text` | `#ad4f08` | `#fcd34d` | 4.52 / 8.25 |
| `--status-info-text` | `#0369a1` | `#7dd3fc` | 4.69 / 7.68 |
| `--status-purple-text` | `#6b21a8` | `#d8b4fe` | 6.92 / 7.27 |
| `--status-danger-text` | `#b91c1c` | `#fca5a5` | 5.08 / 6.95 |
| `--status-neutral-text` | `#475569` | `#cbd5e1` | 6.23 / 8.01 |
| `--status-orange-text` | `#b83a09` | `#fdba74` | 4.68 / 7.42 |
| `--danger-solid` / `--text-on-danger` | `#b91c1c` / `#ffffff` | same | 6.47 |
| inverse surface / text / muted / accent | `#0f172a` / `#e2e8f0` / `#94a3b8` / `#7dd3fc` | same | 14.48 / 6.96 / 10.71 |

Status backgrounds and borders use the tinted pattern from the brief: approximately 12% hue for the background and 30% for the border. Every foreground/background pairing is checked against the requirement: 4.5:1 for body/label/placeholder text, 3:1 for input borders and focus rings, with 0 failures in the final matrix.

The light-theme values differ from the original palette in four intentional ways because the originals did not meet the targets:

- muted/placeholder text moved from `#64748b` to `#586777` so it also passes on tinted surfaces;
- input borders moved from `#cbd5e1` to `#7b8a9e` to reach 3:1;
- warning and orange status text were darkened slightly;
- accent hover moved to `#075985` so white button labels remain readable.

### Base layer and native controls

`Frontend/src/style.css` also owns:

- `color-scheme: light` on `:root` and `dark` on `.dark`;
- `body` background/text and heading colours;
- `accent-color: var(--accent)` for checkboxes and radios;
- visible date-picker indicator handling (`color-scheme` plus an opacity rule in dark mode);
- dark `<select>` option backgrounds;
- `scrollbar-color` with an `::-webkit-scrollbar` fallback;
- a global `:focus-visible` ring using `--accent-text`;
- `prefers-reduced-motion` handling;
- the shared `.card`, `.metric-card`, `.input-field`, `.field-label`, `.field-help`, `.btn-*`, `.badge-*`, `.callout-*` and feedback classes.

`#main-content` is focused programmatically after navigation. It is a landmark, not a control, so its focus outline is explicitly suppressed to prevent the accent ring from drawing a blue box around the whole content area.

### Theme switching

`Frontend/src/composables/useTheme.ts` is the single source of truth for the preference:

- `ThemePreference = 'light' | 'dark' | 'system'`;
- storage key `campusdesk_theme` (the pre-existing key, retained to preserve saved choices);
- `initTheme()` restores the preference, reads `matchMedia('(prefers-color-scheme: dark)')`, subscribes to changes and applies the DOM state;
- `applyThemeToDom()` toggles `.dark`, sets `color-scheme`, and updates `<meta id="theme-color-meta">`;
- `useTheme()` exposes `theme`, `resolvedTheme` and `setTheme()`.

`Frontend/index.html` contains a small blocking script that applies the stored/system theme **before first paint**, so the document does not start in the wrong theme. `ThemeToggle.vue` (renamed from `ThemeSwitcher.vue`) is the one control used in every authenticated header and on auth pages. It offers Light, Dark and System, reflects the active setting with the existing sun/moon/monitor icons, exposes `aria-haspopup`, `aria-expanded`, `aria-checked` and an accessible label, supports arrow keys, Escape and outside-click dismissal, and persists the choice in `localStorage` inside try/catch guards. System mode reacts live to media-query changes.

### Shared components

| Component | Role |
|---|---|
| `ui/AppBadge.vue` | One dot + label badge with `neutral/success/warning/info/purple/danger/orange` tones. Tone classes are literal strings so Tailwind cannot purge them. |
| `ui/AppCallout.vue` | Tinted informational panel using the same tone families (`info/success/warning/danger/neutral`). Literal tone map for the same reason. |
| `StatusBadge.vue` | Maps request/stage status to `AppBadge`: draft neutral, awaiting payment/pending warning, in review info, forwarded purple, ready/collected success, rejected danger. |
| `TierBadge.vue` | Maps Standard to neutral and Fast/Super Fast to purple; replaced the old emoji bolt with the shared dot so colour is not the only signal. |
| `LevelBadge.vue` | Neutral metadata badge. |
| `ui/BaseModal.vue` | Raised modal surface, overlay, token border, scrollable body, focus containment. |
| `ui/EmptyState.vue`, `SkeletonLoader.vue`, `PageHeader.vue` | Shared loading/empty/header presentation using tokens. |
| `admin/AdminPagination.vue`, `AdminAuditTrail.vue`, `AdminDeleteDialog.vue` | Shared admin list/dialog primitives with subtle dividers and `btn-danger`. |
| Form classes | `.input-field`, `.field-label`, `.field-help` are the shared form style set instead of a wrapper component, preserving native labels, validation and autocomplete semantics. |

### App shell and layout

- Sidebar and auth masthead use fixed **inverse** tokens (`--surface-inverse`, `--inverse-*`) so the brand chrome remains dark in both themes.
- The sidebar has a right border; the header has a bottom border. Their 1px edges meet at the same x/y coordinate (`asideRight = headerLeft`, `headerBottom = mainTop`).
- The header is opaque `--surface-card`; the main area uses `--surface-page`.
- The focus ring on `#main-content` is suppressed; interactive controls keep their focus-visible ring.

### Notification inbox

`NotificationBell.vue` keeps the existing authenticated loading, retry, outside-click, Escape and unread-count behaviour and adds:

- **Unread visual language:** a 2px accent left rail, accent dot, tinted `--accent-soft-bg` surface, semibold heading-colour message and explicit “Unread · Mark as read” metadata.
- **Read visual language:** transparent panel surface, normal-weight body text and “Read” metadata.
- **Mark all read:** a header action visible only while unread items exist. It optimistically marks all local rows read, calls `markNotificationRead(id)` for each unread item with `Promise.allSettled`, reverts only failed rows if the API rejects, and announces success through a visually hidden `role="status"`.
- The header also shows an `N unread` badge and a compact Refresh action; the count badge and Mark all action disappear when the inbox is clear.
- Rows are disabled while a single read or the bulk action is in progress.

No bulk endpoint was added. The frontend fan-out reuses the existing `PATCH /api/notifications/{id}/read` contract.

## Implementation decisions

### D-01: Semantic CSS variables, not paired light/dark utilities

**Decision:** use one semantic token set (`--surface-card`, `--text-muted`, etc.) and consume it through Tailwind aliases. Components must not contain raw palette utilities or literal themeable hex values.

**Alternatives:** keep `bg-white dark:bg-slate-900` pairs (rejected: duplication, easy to miss a dark variant); use a component library theme (rejected: adds dependency and overrides); CSS-in-JS (rejected: not the project stack).

**Consequences:** one place to change colour; audit is simple (raw utilities = 0); opacity modifiers on CSS variables are avoided because Tailwind cannot reliably apply alpha to an arbitrary `var()` colour. See ADR-14.

### D-02: Keep the existing `.dark` class and storage key

**Decision:** keep `.dark` on `<html>` and `campusdesk_theme` rather than introducing `data-theme` or renaming storage.

**Reasoning:** this was already the project mechanism and existing users may have a saved preference. The brief's `campusdesk-theme` was an example, not a requirement.

**Consequences:** the pre-paint script and `useTheme.ts` share the same key; changing either must change both.

### D-03: One `ThemeToggle` with Light, Dark and System

**Decision:** rename the single switcher to `ThemeToggle`, use it in every header, default to System and persist explicit choices.

**Alternatives:** separate moon/monitor controls (rejected: two behaviours); binary toggle only (rejected: no System option); native `<select>` (rejected: less consistent with the header).

**Consequences:** all roles share one component; the inline head script remains necessary because Vue mounts after first paint. See ADR-15.

### D-04: Contrast-first token values

**Decision:** adjust tokens until every target passed, even where the brief's suggested values failed: dark input borders use `rgba(255,255,255,.40)` (0.18 measured ~2.1:1), light placeholders use `#586777`, and dark primary buttons keep `#0369a1` with white labels.

**Consequences:** the light theme is not pixel-identical to the previous release; changes are limited to contrast-driven colour adjustments documented above.

### D-05: Literal tone maps in `AppBadge` and `AppCallout`

**Decision:** map props to a static object of full class names (`success: 'badge-success'`) rather than interpolating ``badge-${tone}``.

**Reasoning:** Tailwind’s content scanner cannot see runtime-interpolated class names and purges the rules. This caused Ready, Collected and Rejected to render grey while Forwarded stayed purple only because a test happened to contain the literal string. The same defect affected callout tones.

**Consequences:** adding a tone now requires adding a literal map entry and a test row. `BadgeTones.spec.ts` locks the current mapping in.

### D-06: No token classes inside scoped `@apply`

**Decision:** scoped component styles declare token colours with `var(--token)` instead of `@apply border-subtle bg-surface-card`. `@apply` is limited to core layout utilities.

**Reasoning:** Tailwind resolves `@apply` at compile time. A dev server started before a token/config change keeps the old config cached, throwing “class does not exist” in `StaffCaseCard.vue`. That single CSS failure broke the whole stylesheet in dev, which in turn made the theme dropdown transparent and removed other utilities.

**Consequences:** scoped styles are slightly more verbose but compile independently of Tailwind config freshness. Restarting the dev server after Tailwind config changes remains the safe habit.

### D-07: Suppress the main-region focus ring only

**Decision:** add `#main-content:focus, #main-content:focus-visible { outline: none; }` while keeping the global focus ring for controls.

**Reasoning:** route navigation focuses `<main>` for screen readers. The global accent ring drew a 2px blue box around the content area on initial load, which looked like a theme border/glow and disappeared after a click.

**Consequences:** keyboard focus is still moved to the main landmark, but the landmark itself does not paint a ring; interactive elements retain visible focus.

### D-08: Fixed inverse chrome

**Decision:** sidebar and auth masthead use `--surface-inverse` and `--inverse-*` tokens that are identical in both themes.

**Reasoning:** the navy sidebar is brand chrome, not a themed content surface. Flipping it to light in the light theme would change the established layout and make the wordmark/nav treatment inconsistent.

**Consequences:** nested content in the sidebar uses inverse text tokens; themeable surfaces elsewhere never use white in dark mode.

### D-09: Badge tone mappings

**Decision:** Standard fee chips are neutral; Fast Mode and Super Fast Mode are purple; status mappings are draft neutral, pending/awaiting payment warning, in review info, forwarded purple, ready/collected success, rejected danger, reopened orange.

**Consequences:** Fast and Super Fast share a hue but keep distinct labels; colour is always accompanied by text and a dot.

### D-10: Multiple unread signals

**Decision:** combine accent rail, dot, tinted surface, font weight and explicit Unread/Read text.

**Alternatives:** tinted background only (rejected: colour-only and too subtle in dark mode); “New” pill only (rejected: noisier); separate New/Earlier sections (deferred: more markup, changes list semantics).

**Consequences:** the panel looks denser but remains scannable; every signal is CSS, so no API change is required.

### D-11: Mark all read via frontend fan-out

**Decision:** reuse the existing per-notification endpoint with `Promise.allSettled`, optimistic updates and per-row rollback. No backend change.

**Alternatives:** a new bulk endpoint (best at scale, but requires Laravel changes outside this frontend work); client-only state (rejected: not persisted); disabling the action (not acceptable UX).

**Consequences:** one PATCH per unread item; no atomic server transaction; a partial outage can leave a mixed state that the UI reports and partially reverts. At larger inbox sizes this should be replaced by a single bulk endpoint. See ADR-16.

### D-12: No new runtime dependencies or backend changes

**Decision:** solve everything with the existing Vue, Tailwind and Axios stack.

**Consequences:** axe-core was used only as a temporary audit tool outside the dependency tree; daisyUI remains installed but disabled; no package or lockfile change was made.

## Screens and defects covered

| # | Screen / defect | Resolution |
|---|---|---|
| 1 | All requests — near-white filter panel, pale heading, inverted inputs | Panel `--surface-input`, heading `--text-heading`, labels `--text-muted`, inputs `.input-field`. |
| 2 | Create request type — white Tier #1 card, invisible labels, light arrows | Nested `--surface-input` card, `.field-label`, `btn-secondary` arrows, accent checkbox. |
| 3 | New request — washed-out “What happens next?” card | Shared `AppCallout` on the info pair. |
| 4 | New request — low-contrast upload zone and Choose files control | `--border-strong` dashed border, muted helper text, bordered/toned file button. |
| 5 | All requests — grey checkbox and default date picker | `accent-color`, `color-scheme`, visible calendar indicator. |
| 6 | Requests and Status history — muted metadata below 4.5:1 | `--text-muted` measured at 5.05:1 light / 5.41:1 dark. |
| 7 | People & access — bright dividers, light Staff/Enabled chips | `divide-subtle`; neutral/success `AppBadge`. |
| 8 | Request types — light fee chips | `AppBadge`; Standard neutral, Fast/Super Fast purple. |
| 9 | Campus overview — flat metric cards and inconsistent pills/dividers | Card surface/border, shared status badges, subtle dividers, heading numbers. |
| 10 | Status history — dim filter inputs | Placeholder/label tokens verified at target ratios. |

Every other screen was swept to the same tokens: login, registration, student overview/requests/new/detail, staff overview/queue/cases, department-admin overview/requests, super-admin overview/requests/users/faculties/departments/programmes/request-types/history, document viewer, request timeline, notification panel, modal dialogs, empty states and feedback banners.

## Tradeoffs and limitations

### Mark all as read

- Sends **one PATCH per unread notification**. This is simple and needs no API work, but it is not atomic and becomes inefficient for large inboxes.
- Successful and failed rows can diverge; the component reverts only failed rows and reports the partial failure. The server remains authoritative after a refresh.
- A real-time invalidation arriving while the bulk action is running can replace `items` and race the local snapshot. The next server refresh corrects the display.
- The endpoint returns an updated notification per item; the component ignores those payloads and keeps its optimistic values.
- The action is hidden for zero unread items and disabled while loading, a single read or another bulk action is in progress.
- Success is announced through a visually hidden status only, not a visible toast.

### Theme and tokens

- The pre-paint script in `index.html` and `useTheme.ts` both encode the storage key, class name and meta colours. They must be kept in sync; the script cannot import TypeScript.
- Browser `theme-color` meta values are hardcoded because a meta tag cannot resolve a CSS custom property.
- Tailwind opacity modifiers are not used with `var()` colours; tinted alpha values are defined as explicit tokens instead.
- Light-theme surfaces change slightly because the previous placeholder/muted and border values failed contrast.
- A few light status ratios sit just above 4.5:1 (success 4.51, warning 4.52). Any palette change must re-run the contrast matrix.
- Automatic `prefers-contrast` / forced-colors support and high-contrast mode were not tested.
- The fixed inverse sidebar is intentional and does not follow light/dark card surfaces.

### Notifications

- The inbox still fetches the whole collection through `GET /notifications`; there is no pagination or server-provided unread count in the panel.
- Unread state is conveyed by both text and visuals, but the row itself is a button with an `aria-label`; screen-reader verification in a real browser remains recommended.
- The panel shows all loaded notifications; there is no “New”/“Earlier” grouping or per-notification timestamp grouping.

### Verification

- Authenticated screens were rendered in headless Chrome against a temporary fixture API because the local Laravel backend was not running. This proves markup, tokens, layout and interaction states, not real workflow data or backend permissions.
- Native OS date/select popups cannot be captured by headless screenshots; their dark rendering was checked through computed `color-scheme` and widget styles.
- No Playwright/Cypress visual-regression suite exists in the repository, so these checks are not enforced in CI.
- axe reported zero violations on the audited screens; a handful of “incomplete colour-contrast” items remain benign (a partially obscured textarea and non-text arrow glyphs).

## Verification record

### Automated gates

| Check | Result |
|---|---|
| `npm run typecheck` (`vue-tsc --noEmit`) | Passed |
| `npm run lint` (`eslint .`) | Passed |
| `npm test` | **25 test files, 209 tests passed** |
| `npm run build` (`vite build`) | Passed |
| `git diff --check` | Clean |

The suite grew from 189 tests / 24 files at the start of the work to 209 tests / 25 files. New coverage includes badge/callout tone classes and status mappings (`BadgeTones.spec.ts`), plus notification mark-all success, partial-failure and rollback behaviour (`NotificationBell.spec.ts`).

### Contrast

A throwaway WCAG script checked text, placeholders, status text, input borders, focus rings and button labels across page, card, raised, input, muted and accent-soft surfaces in both themes. Final result: **0 failures**. The minimum values are recorded in the token table above.

### Accessibility

axe-core 4.10.2 (WCAG 2 A/AA tags) was run against:

- All requests — dark and light, 1440 and 390 widths;
- People & access — dark and light;
- Request types and the Create request type modal with a tier — dark and light;
- Status history — dark and light;
- New request — dark and light at 1440, dark at 390;
- Staff cases — dark and light;
- Notification panel — dark at 390px mobile; its light state was reviewed visually;
- Theme menu — dark and light.

Result: **0 violations** in every run. Remaining “incomplete” colour-contrast nodes are limited to a partially obscured textarea and non-text glyphs (arrows/checks); they are not failures.

### Visual and interaction checks

- Screens reviewed in dark and light at 1440/1280 and 390 widths: login, campus overview, all requests, people & access, request types, create-type modal, status history, new request, request detail/payment, staff cases, unclaimed queue, department-admin requests, notification panel, theme menu.
- No white/light card, chip or callout appeared in dark mode.
- No horizontal overflow was introduced (`scrollWidth` stayed within the viewport at 1280 and 390).
- Sidebar/header/main geometry: `asideRight = headerLeft`, `headerBottom = mainTop`, both 1px borders; no blue outline on initial load.
- Native controls in dark mode computed as: select/textarea background `rgb(30,41,59)`, text `rgb(248,250,252)`, placeholder `rgb(148,163,184)`, `color-scheme: dark`, checkbox accent `rgb(3,105,161)`, scrollbar `rgb(71,85,105)` on `rgb(11,17,32)`.
- Theme menu computed background `rgb(22,32,54)` (opaque) in dark and white in light; notification panel is likewise opaque.
- Theme switching: Light, Dark and System; System followed `prefers-color-scheme` from dark to light live; choice survived reload; the pre-paint script applied the class before app mount.
- Notification panel: unread count decreases from 1 to 0 after Mark all read; rows switch to Read; count badge and action disappear; success status text is set.

## Known pitfalls for future work

1. **Never interpolate Tailwind tone classes.** Use the literal maps in `AppBadge`/`AppCallout`, or add the full class strings to the source. Dynamic `badge-${tone}` strings are purged.
2. **Do not put semantic token classes inside scoped `@apply`.** Use explicit `var(--token)` declarations. A stale Tailwind config makes `@apply border-subtle` fail and can blank the entire stylesheet.
3. **Restart the dev server after changing `tailwind.config.js`.** Vite/PostCSS does not always pick up the new token set in a running process; the first symptom is a `class does not exist` overlay in a scoped style.
4. **Avoid opacity modifiers on `var()` colours.** `bg-surface-card/80` is not reliable with a plain custom-property value; define an explicit alpha token instead.
5. **Do not add a global focus-visible ring to `#main-content`.** It is focused on every route change and will draw a blue frame around the content area.
6. **Keep inverse surfaces on inverse tokens.** The sidebar/auth masthead are intentionally dark in both themes; do not replace them with `--surface-card`.
7. **Re-run the contrast matrix after token changes.** Several light values have deliberately tight margins.
8. **Update both theme scripts when changing the storage key or class.** `index.html` and `useTheme.ts` must stay in sync.
9. **When adding a tone, add the literal map entry and a test.** Tailwind and the test suite both depend on it.
10. **Do not assume native picker popups are covered by screenshots.** Verify `color-scheme` and computed styles; real OS popups need a manual check.

## Future improvements

1. **Bulk mark-all endpoint** — `PATCH /api/notifications/read-all` that updates all unread rows for the authenticated user in one query, returns the updated count/content, and removes the fan-out and partial-failure tradeoff. This is the recommended next change if inbox sizes grow.
2. **Server unread count and pagination** — return an unread count with the notification collection or add a dedicated endpoint; paginate older notifications and load more on demand.
3. **Token guardrail in CI** — a Stylelint/ESLint rule or custom script that fails on raw palette utilities (`bg-white`, `text-slate-*`) and themeable hex values in `src/**/*.vue`.
4. **Visual regression and axe in CI** — add Playwright (or Cypress) with a fixture API, screenshot both themes at 390/1440, and run axe on the critical screens.
5. **Centralize theme constants** — generate the `index.html` pre-paint script or expose a tiny runtime config so the storage key, class name and meta colours have one source.
6. **Remove obsolete assets** — drop the unused daisyUI dependency, the legacy concrete palette aliases, and the now-unused `.badge-tier-*` classes after confirming no consumers remain.
7. **Notification grouping** — a visually separated “New” group above “Earlier” notifications, if the inbox grows; keep the current text/dot signals.
8. **Visible mark-all feedback** — a short toast or inline confirmation in addition to the screen-reader status, if product wants stronger acknowledgement.
9. **Forced-colors/high-contrast audit** — verify Windows High Contrast and `prefers-contrast` rendering, especially for tinted status pills.
10. **Backend alignment for notifications** — if a bulk endpoint is added, add feature tests for ownership scoping, idempotency and the returned unread count; do not trust a client-supplied user ID.

## Files changed or created

### Theme foundation

| File | Change |
|---|---|
| `Frontend/src/style.css` | Full light/dark token set, base layer, native controls, scrollbars, focus rules, shared component classes. |
| `Frontend/tailwind.config.js` | Semantic aliases for surfaces, borders, text, accent-soft, inverse chrome, status and danger tokens. |
| `Frontend/tailwind.config.ts` | Re-exports the JS config so it cannot drift. |
| `Frontend/index.html` | Dark `theme-color` meta aligned to `#020617`; pre-paint theme script retained. |
| `Frontend/src/composables/useTheme.ts` | Typed legacy media listener, meta constants, shared theme application. |
| `Frontend/src/composables/__tests__/useTheme.spec.ts` | Updated meta-colour expectation. |

### Shared UI

| File | Change |
|---|---|
| `Frontend/src/components/ui/AppBadge.vue` | Tone API with literal class map and dot + label. |
| `Frontend/src/components/ui/AppCallout.vue` | New tinted callout with literal tone map. |
| `Frontend/src/components/ui/BaseModal.vue` | Tokenised header divider. |
| `Frontend/src/components/ui/EmptyState.vue` | Tokenised icon tile and text. |
| `Frontend/src/components/ui/SkeletonLoader.vue` | Tokenised skeleton surfaces. |
| `Frontend/src/components/ui/PageHeader.vue` | Muted description token. |
| `Frontend/src/components/StatusBadge.vue` | Composes `AppBadge` with status tone mapping. |
| `Frontend/src/components/TierBadge.vue` | Composes `AppBadge`; Standard neutral, Fast/Super Fast purple. |
| `Frontend/src/components/LevelBadge.vue` | Neutral `AppBadge`. |
| `Frontend/src/components/ThemeSwitcher.vue` → `ThemeToggle.vue` | Renamed, tokenised, opaque raised menu, accessible state. |
| `Frontend/src/components/__tests__/ThemeSwitcher.spec.ts` → `ThemeToggle.spec.ts` | Renamed and updated. |
| `Frontend/src/components/__tests__/TierBadge.spec.ts` | Updated for shared tones. |
| `Frontend/src/components/__tests__/BadgeTones.spec.ts` | New tone/status mapping coverage. |
| `Frontend/src/components/__tests__/RequestTimeline.spec.ts` | Updated node selectors and expected tone classes. |

### Application shell and feature components

| File | Change |
|---|---|
| `Frontend/src/App.vue` | Shell/sidebar/header tokens, inverse sidebar border, `ThemeToggle`, main focus ring suppressed via CSS. |
| `Frontend/src/components/layout/AuthLayout.vue` | Inverse masthead tokens and heading colour fix. |
| `Frontend/src/components/DocumentViewer.vue` | Attachment/preview surface and accent-state tokens. |
| `Frontend/src/components/NotificationBell.vue` | Unread rail/dot/tint/weight/text, count badge, Mark all read with optimistic update and rollback. |
| `Frontend/src/components/__tests__/NotificationBell.spec.ts` | Load/retry plus mark-all success, failure and rollback coverage. |
| `Frontend/src/components/RequestTimeline.vue` | Stage node, connector and metadata tokens. |
| `Frontend/src/components/admin/AdminAuditTrail.vue` | Subtle dividers and semantic text. |
| `Frontend/src/components/admin/AdminDeleteDialog.vue` | Token text and `btn-danger`. |
| `Frontend/src/components/admin/AdminPagination.vue` | Subtle border and muted text. |
| `Frontend/src/components/admin/AdminUserFields.vue` | Labels, nested panel, staff chip and checkbox tokens. |
| `Frontend/src/components/staff/StaffCaseCard.vue` | Scoped styles use explicit token variables; fixes the dev compile error. |
| `Frontend/src/components/staff/StaffCaseWorkspace.vue` | Scoped styles use explicit token variables; tokenised status panels/tabs. |
| `Frontend/src/components/student/StudentPaymentPanel.vue` | Warning/accent/danger panels, operator selectors, tier switcher and simulator tokens. |
| `Frontend/src/components/student/StudentRequestCard.vue` | Card, progress, link and border tokens. |
| `Frontend/src/components/student/StudentRequestForm.vue` | Form, upload zone, tier cards and error tokens. |

### Views

| File | Change |
|---|---|
| `Frontend/src/views/LoginView.vue` | Accent link tokens. |
| `Frontend/src/views/RegisterView.vue` | Error and helper text tokens. |
| `Frontend/src/views/admin/AdminHistoryPage.vue` | Filter labels/placeholders and audit dividers. |
| `Frontend/src/views/admin/AdminOverviewPage.vue` | Metric accents and activity dividers. |
| `Frontend/src/views/admin/AdminReferencesPage.vue` | Tier editor surfaces, labels, buttons and fee badges. |
| `Frontend/src/views/admin/AdminRequestsPage.vue` | Filter panel, status/counter badges and metadata. |
| `Frontend/src/views/admin/AdminUsersPage.vue` | Staff/Enabled/Disabled badges and action colours. |
| `Frontend/src/views/dept-admin/DeptAdminOverviewPage.vue` | Muted metrics and hints. |
| `Frontend/src/views/dept-admin/DeptAdminRequestsPage.vue` | Table, status text and badges. |
| `Frontend/src/views/staff/StaffOverviewPage.vue` | Muted labels and metrics. |
| `Frontend/src/views/student/StudentNewRequestPage.vue` | Info callout and warning banner. |
| `Frontend/src/views/student/StudentOverviewPage.vue` | Metric and error colours. |
| `Frontend/src/views/student/StudentRequestDetailPage.vue` | Detail, payment and reopen panels. |
| `Frontend/src/views/student/StudentRequestsPage.vue` | Filter/list/link tokens. |

## Commit record

| Commit | Date | Purpose |
|---|---|---|
| `3b14ae0` | 17 Nov 2026 | Finalise semantic tokens and native-control base layer (Phases 1–2). |
| `2610e14` | 17 Nov 2026 | Route badges and shared primitives through tokens (Phase 3). |
| `3c5bd55` | 17 Nov 2026 | Tokenise every screen and unify the theme toggle (Phases 4–5). |
| `d096046` | 17 Nov 2026 | Use the purple tone for fast-tier fee chips. |
| `610f6fc` | 18 Nov 2026 | Restore badge tone emission, opaque menus, scoped-style hardening and clean section borders. |
| `1a11dff` | 18 Nov 2026 | Distinguish unread notifications and add Mark all read. |

Phases 1–2 share one stylesheet change, and Phases 4–5 share the screen/toggle sweep, so they are recorded as combined commits to keep every commit buildable.

## Related documentation

- [DECISIONS.md](DECISIONS.md) — ADR-14 (tokens/dark theme), ADR-15 (ThemeToggle), ADR-16 (mark all read).
- [UI_UX.md](UI_UX.md) — current design-system, component and responsive conventions.
- [KNOWN_ISSUES.md](KNOWN_ISSUES.md) — resolved theme pitfalls and preventive guidance.
- [PROJECT_HISTORY.md](PROJECT_HISTORY.md) — Phase 15 timeline entry.
- [TESTING.md](TESTING.md) — test suites and recommended manual checklist.
- [FRONTEND_REDESIGN.md](FRONTEND_REDESIGN.md) — earlier frontend redesign and verification record.
