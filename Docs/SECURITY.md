# CampusDesk — Security Documentation

## Authentication Mechanism

**Laravel Sanctum 4.x, Bearer token mode** (not cookie/session mode).

- Tokens issued via `$user->createToken('auth_token')->plainTextToken` on login/register
- Tokens stored hashed in `personal_access_tokens` table
- Plain text token is only ever visible once, in the login/register HTTP response
- Frontend stores the plain token in `localStorage`
- Every subsequent request attaches `Authorization: Bearer {token}` via an Axios request interceptor in `api.ts`
- `auth:sanctum` middleware validates the token on every protected route

**Explicitly NOT used:** `EnsureFrontendRequestsAreStateful` (Sanctum's cookie-based SPA middleware) — this was tried, caused a redirect-based CORS failure, and was removed. See KNOWN_ISSUES.md.

## Password Handling

- Passwords hashed via `Hash::make()` (bcrypt, Laravel default) on registration
- Login validated via Breeze-provided `LoginRequest::authenticate()` flow
- Password reset backend routes exist (from Breeze scaffolding) but no frontend UI was built for them

## Role-Based Authorization

Three layers, in order of enforcement:

1. **Route middleware** (`student`, `staff`, `dept_admin`, `super_admin`) — coarse role gating at the routing layer
2. **Form Request `authorize()`** — per-endpoint role checks using `$this->user()->role` directly (NOT `Gate::allows()`, which is unreliable in the stateless API context)
3. **Controller-level ownership/business-rule checks** — e.g., `RequestController::show()` checks `$request->student_id === Auth::id() || Auth::user()->role === 'staff'`; `RequestStageController::claim()` checks department membership and predecessor-stage approval

The department-admin gate and middleware both use `is-dept-admin`. The protected department-admin routes are active.

## File Upload Security

- Validated file types: `pdf`, `docx`, `jpg`, `png` (via `mimes:` validation rule in `StoreRequestRequest`)
- Max file size: 5MB per file (`max:5120`)
- Files stored OUTSIDE the public webroot (`storage/app/attachments/`, not `storage/app/public/`)
- Files served exclusively through `AttachmentController::show()`, which checks:
  - Requester is authenticated (Bearer token via `auth:sanctum`)
  - Requester is either the owning student OR has `role = 'staff'`
  - File exists on disk (`Storage::exists($attachment->file_path)`)
- No direct public URL ever exposes an attachment

## API Security Measures

### Rate Limiting

| Route group | Limit | Reasoning |
|---|---|---|
| `POST /api/register` | 5/minute (`throttle:5,1`) | Prevents account creation abuse |
| `POST /api/login` | 5/minute (`throttle:5,1`) | Prevents credential-stuffing/brute-force |
| `POST /api/requests` | 10/minute (`throttle:10,1`) | Multiple DB writes per call |
| `GET /api/requests`, `GET /api/requests/{id}` | 60/minute (`throttle:60,1`) | Read-only, lower risk |
| Staff routes (`/stages`, `/claim`, `/resolve`) | 60/minute | Standard limit |
| Super Admin routes (`/api/admin/*`) | 60/minute | Protected management and oversight |
| Email verification routes | 6/minute | Breeze default |
| `GET /api/attachments/{id}` | No explicit rate limit | ⚠️ See gaps below |

### Input Validation

All mutating endpoints use Form Request classes (`StoreRequestRequest`, `UpdateStageStatusRequest`) or inline `$request->validate()` (registration). Validation includes:
- `exists:` rules for all foreign key references
- `mimes:` and `max:` rules for file uploads
- `in:` rules for enum fields (`status`, `level`)
- `required_if:` for conditional requirements (`staff_note` required when rejecting)

### CORS

Configured in `config/cors.php`:
```php
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
'supports_credentials' => true,
'paths' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```

`HandleCors` middleware explicitly prepended in `bootstrap/app.php` to guarantee it runs before any other middleware.

## Data Integrity / Concurrency Security

The **multi-claim concurrency bug** (see KNOWN_ISSUES.md) was a data-integrity/business-logic issue — it allowed two staff members to simultaneously act on sequentially-dependent stages. The fix uses:
- SQL-level predecessor-approval filtering in the queue query (`whereExists` correlated subquery)
- `DB::transaction()` + `->lockForUpdate()` in the claim endpoint to close the TOCTOU window

PHPUnit regression tests in `SequentialRoutingPreservationTest` lock this behaviour in.

## Known Security Weaknesses / Gaps

1. **Logout revocation** — `AuthenticatedSessionController::destroy()` deletes the current Sanctum token. Previously documented session-method failures have been resolved.

2. **No token expiration configured** — `sanctum.expiration` is `null` (tokens never expire automatically). No token refresh mechanism exists. A leaked or stolen token remains valid until manually revoked.

3. **No rate limiting on `GET /api/attachments/{id}`** — could theoretically be used to enumerate attachment IDs, though the ownership/staff check blocks unauthorized access to content.

4. **Admin authorization** — `/api/admin` requires Sanctum, the Super Admin gate, and throttling. Department-admin endpoints are limited to the primary department.

5. **No CSRF protection needed/considered** — since the app uses Bearer tokens exclusively (stateless), CSRF is not applicable to API routes. This is correct for the chosen auth strategy.

6. **Status history access** — `/api/admin/audit-log` is Super Admin only. It records request/stage transitions, not administrative CRUD or privilege changes; those require the separate task 9 audit table.

7. **Email content includes request details** — `RequestStatusUpdated` mailable includes request type and status. Standard for this kind of system; no additional sensitivity controls have been discussed.

8. **`.env` is gitignored** — confirmed via `campusdesk/.gitignore`. No secrets are committed to the repository.

9. **`personal_access_tokens` table is manually migrated** — Sanctum tokens are stored in `personal_access_tokens` via migration `2026_04_12_232151_create_personal_access_tokens_table`. This is redundant with Sanctum's own migration. Verify this does not cause conflicts (no issues observed in practice).

## Deferred Integration Security Requirements

- **AI support:** External models must never receive passwords, bearer tokens, attachments, or unredacted personal data. AI tools must be read-only and allowlisted; all workflow changes remain human-authorized API actions. Human escalation transcripts require role-scoped access and retention rules.
- **Event delivery:** Outbound webhooks require per-consumer secrets, HMAC signatures, timestamp/replay protection, idempotency keys, bounded retries, and delivery audit logs. Browser clients should use authenticated, authorization-scoped channels rather than public subscriptions.
- **Payments:** Provider credentials and webhook secrets belong only in environment configuration. Webhook verification precedes any database write; provider transaction IDs must be unique; payment state changes must be idempotent, locked, logged, and never trusted from the browser redirect alone.
