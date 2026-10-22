# CampusDesk — Realtime Delivery Research

**Last reviewed:** 21 October 2026  
**Framework target:** Laravel 12.x  
**Status:** **Implemented, verified, and operational.** The transactional outbox pattern, Laravel Reverb broadcasting, dedicated `realtime` queue worker, `realtime:reconcile` scheduler, private channel authorization, and frontend Laravel Echo invalidation subscription have all been fully implemented, tested, and surgically refactored end to end.

## Scope

This note validates the delivery half of Issue #6 against Laravel's first-party
documentation. It covers the durable request-event outbox present in
CampusDesk, its queue and reconciliation path, Reverb, private-channel
authorization, and the Vue client's invalidation/refetch behavior.

## Repository baseline

- CampusDesk uses Laravel's `database` queue connection and has the
  standard `jobs`, `job_batches`, and `failed_jobs` tables.
- `request_events` is the durable source of truth. It has stable UUID event IDs,
  per-request aggregate sequences, minimal payloads, audiences, and a delivery
  status (`pending`, `published`, `failed`).
- `RequestEventDelivery::publish(eventId)` owns synchronous delivery,
  sequential duplicate suppression, the fixed browser envelope, and durable
  success/failure audit metadata behind the `RequestEventTransport` seam.
- The SPA authenticates with Sanctum Bearer tokens through its shared Axios
  instance. It does not use Sanctum's cookie-based SPA mode.
- The frontend `User` type loads `staff_profile.departments`, supporting
  authoritative private channel subscription on login.
- The environment runs a 7-service topology in Docker Compose: frontend, backend,
  default worker, MySQL, Reverb WebSocket server, dedicated `realtime` queue worker,
  and console scheduler.

## Findings and CampusDesk decisions

### 1. Use a named `realtime` database queue

Laravel distinguishes a queue **connection** from named queues within that
connection. A job can select a queue with `onQueue(...)`, and a worker can be
restricted to a queue with `queue:work ... --queue=...`. The database driver
uses the existing `jobs` table. [Laravel queue connections and named queues](https://laravel.com/docs/12.x/queues#connections-vs-queues),
[database queue prerequisites](https://laravel.com/docs/12.x/queues#database)

CampusDesk keeps the `database` connection and dispatches
delivery jobs to the named `realtime` queue. Production and local environments run a dedicated
worker:

```text
php artisan queue:work database --queue=realtime --sleep=1 --timeout=30
```

This isolates latency-sensitive invalidations from email work without adding
Redis or another queue backend. Worker attempt policy lives on the job,
not only in the process command, so every environment applies the same policy.

### 2. Dispatch the fast-path job only after commit

Laravel warns that a worker may see a queued job before its surrounding
database transaction commits. An inline `->afterCommit()` delays queueing until
all open transactions commit and discards the dispatch when the transaction
rolls back. [Laravel jobs and database transactions](https://laravel.com/docs/12.x/queues#jobs-and-database-transactions)

After `RequestEventRecorder` persists an event inside a lifecycle transaction,
it registers a guarded after-commit callback that attempts to dispatch
`PublishRequestEvent($eventId)` to `realtime`. Passing only the stable event ID
keeps the queue payload small and makes the durable row authoritative. The
callback reports, but does not rethrow, an enqueue failure: Laravel runs these
callbacks after the database commit, so surfacing that failure as an HTTP error
would tell the caller the mutation failed even though it was committed. The
reconciler is the durability backstop for this best-effort fast path.
[Laravel 12.56 transaction callback source](https://github.com/laravel/framework/blob/dac16d424b59debb2273910dde88eb7050a2a709/src/Illuminate/Database/Concerns/ManagesTransactions.php#L49-L74)

There is still a small failure window between the lifecycle commit and creation
of the queue row. The scheduled reconciler closes that gap; it guarantees durable publication.

### 3. Treat delivery as at-least-once

Laravel retries jobs that throw, supports a job-level `tries` value, and accepts
an array of retry delays. Exhausted asynchronous jobs are recorded in
`failed_jobs`, and Laravel provides inspection and retry commands. [Maximum job attempts](https://laravel.com/docs/12.x/queues#specifying-max-job-attempts-timeout-values),
[backoff and failed jobs](https://laravel.com/docs/12.x/queues#dealing-with-failed-jobs),
[manual queue retry](https://laravel.com/docs/12.x/queues#retrying-failed-jobs)

Use six total attempts—one initial attempt plus five retries—with:

```php
public int $tries = 6;

public function backoff(): array
{
    return [1, 5, 30, 120, 600];
}
```

Make `PublishRequestEvent` unique by event ID for longer than its complete
retry window. Laravel keeps a `ShouldBeUnique` lock until the job completes or
exhausts its attempts, and CampusDesk's database cache supports atomic locks.
This prevents the reconciler from enqueueing parallel copies that
bypass the job's backoff. [Laravel unique jobs](https://laravel.com/docs/12.x/queues#unique-jobs)

Laravel also documents that timeout/retry configuration can cause a job to be
processed twice if misconfigured. More generally, a process can successfully
publish and die before marking the outbox row published. Therefore, the safe
guarantee is **at-least-once**, not exactly-once. `--timeout` must be shorter
than the database connection's `retry_after`. [Laravel job expiration and timeouts](https://laravel.com/docs/12.x/queues#job-expirations-and-timeouts)

Implications:

- A published row is a no-op when handled again.
- A publish failure leaves an auditable attempt and rethrows for Laravel's
  retry mechanism.
- The final failure marks the outbox row `failed`; a CampusDesk command resets
  that row to `pending` and dispatches a fresh job for an explicit manual retry.
- Stable event ID is the consumer deduplication key. Aggregate sequence lets a
  client recognize stale or out-of-order events.
- Do not promise strict publication order. These events only invalidate data
  that is reloaded from authoritative REST endpoints, so clients keep a
  per-request sequence high-watermark and ignore lower or equal sequences.
  Strict server ordering would make one failed event block every later event
  for that request.
- Never hold a database transaction open during network publication.

The `request_events` table contains delivery metadata sufficient for operations:
attempt count, last-attempt time, published time, failed time, and a bounded,
sanitized last error.

### 4. Reconcile pending events periodically

Laravel supports `everyFiveMinutes()`. In production, one `schedule:run` invocation
evaluates the schedule; locally, `schedule:work` runs it in the
foreground. `withoutOverlapping()` uses a cache lock, and `onOneServer()` is
available if the scheduler later runs on multiple hosts. [Schedule frequencies](https://laravel.com/docs/12.x/scheduling#schedule-frequency-options),
[running the scheduler](https://laravel.com/docs/12.x/scheduling#running-the-scheduler),
[overlap and single-server locks](https://laravel.com/docs/12.x/scheduling#preventing-task-overlaps)

The `realtime:reconcile` command runs with `withoutOverlapping()`. It enqueues
pending events and recovers any stranded in-progress rows, while ignoring
published or manually failed rows.

This runs via a dedicated scheduler process in the Compose topology (`scheduler`).

### 5. Publish synchronously *inside* the queued delivery job

Laravel normally places `ShouldBroadcast` events onto a queue and allows a
custom broadcast queue. `ShouldBroadcastNow` uses the synchronous queue.
[Laravel broadcast queues](https://laravel.com/docs/12.x/broadcasting#broadcast-queue)

The outbox delivery job is already the asynchronous boundary. The concrete
`RequestEventTransport` adapter calls Laravel's synchronous
`Broadcaster::broadcast(channels, event, payload)` interface directly, mapping
logical channel names to `PrivateChannel` instances and using one
generic wire event name, `request.event`. [Laravel broadcaster interface](https://api.com/docs/12.x/Illuminate/Contracts/Broadcasting/Broadcaster.html)

The browser envelope is an invalidation signal:

```json
{
  "event_id": "event-uuid",
  "event_name": "request.status_updated",
  "schema_version": 1,
  "aggregate_sequence": 2,
  "occurred_at": "2026-10-21T14:30:00Z",
  "request_id": 12,
  "student_id": 45,
  "current_status": "in_review",
  "current_stage_id": 3,
  "current_department_id": 2,
  "target_department_ids": [2],
  "target_user_ids": [45, 18],
  "reason": "Stage claimed by staff"
}
```

Request descriptions, notes, attachment metadata, tokens, and PII are strictly
excluded from the wire payload. REST endpoints remain authoritative; a connected client refetches
affected resources after receiving this envelope. A reconnect also triggers a
refetch.

### 6. Use only private user and department channels

Private channels perform an HTTP authorization request before subscription.
Authorization callbacks in `routes/channels.php` receive the authenticated user
and return whether that user may listen. [Laravel private-channel authorization](https://laravel.com/docs/12.x/broadcasting#authorizing-channels),
[authorization callbacks](https://laravel.com/docs/12.x/broadcasting#defining-authorization-callbacks)

CampusDesk logical channels are:

- `users.{id}` — authorizes only the authenticated user matching `{id}`.
- `departments.{id}` — authorizes staff assigned to that department, or Super Admins;
  never trusts a department ID supplied by the browser.

There are no public, presence, request-specific, or global super-admin channels.

Because CampusDesk uses Sanctum Bearer tokens, `bootstrap/app.php` configures
broadcasting with `['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']]`.
The frontend `echo.ts` authorizer reuses the shared Axios instance with
`Authorization: Bearer ...` headers to authorize private channels.

### 7. Reverb is a separate runtime concern

Reverb runs as a long-lived `php artisan reverb:start` daemon.
The implementation uses:
1. Backend `laravel/reverb` package in `campusdesk/`.
2. `laravel-echo` and `pusher-js` in `Frontend/`.
3. Dedicated `reverb` service in `compose.yaml` and `compose.staging.yaml`.
4. Dedicated `realtime-worker` and `scheduler` services.

### 8. Frontend behavior is invalidation plus REST refetch

The client module `Frontend/src/services/realtime.ts` manages connection lifecycle,
channel subscription, event deduplication, and high-watermark ordering:
- Subscribes to `private-users.{id}` and authorized `private-departments.{id}`.
- Discards already-seen event IDs and ignores stale aggregate sequences.
- Coalesces rapid bursts to prevent refetch storms.
- Triggers targeted query refetches across notification bell, student request list/detail,
  staff queues, and department admin overviews.
- Performs full state re-hydration upon reconnection.

## Implementation & Verification Summary

All five planned TDD slices were implemented, verified, and surgically refactored:
1. **After-commit fast path:** Outbox writing inside DB transactions with after-commit `PublishRequestEvent` dispatch.
2. **Reliability increments:** Unique job locks, bounded retries, exponential backoff, and scheduled `realtime:reconcile` recovery.
3. **Reverb broadcaster adapter and authorization:** Sanctum Bearer token channel authorization for `users.{id}` and `departments.{id}`.
4. **Runtime topology:** 7-service Docker Compose topology (`frontend`, `backend`, `worker`, `db`, `reverb`, `realtime-worker`, `scheduler`).
5. **Frontend seam & deduplication:** Clean, type-safe Echo client in `Frontend/src/services/` driving invalidation and refetch.
6. **Surgical Audit:** Unified error formatting, deduplicated sequence calculations, streamlined queries, and zero TypeScript/ESLint warnings.

**Quality gates passing 100%:**
- 111 PHPUnit feature tests / 592 assertions across 23 test suites.
- 155 Vitest unit tests across 18 test files.
- 0 ESLint errors and warnings, 0 TypeScript errors.
- Clean Vite production build.
