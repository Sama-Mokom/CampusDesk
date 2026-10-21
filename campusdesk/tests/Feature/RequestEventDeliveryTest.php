<?php

namespace Tests\Feature;

use App\Contracts\RequestEventTransport;
use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestType;
use App\Models\User;
use App\Services\RequestEventDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class RequestEventDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_request_event_is_published_to_its_private_audiences(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00 UTC');

        $event = $this->makePendingEvent();
        $transport = new RecordingRequestEventTransport;

        Carbon::setTestNow('2026-10-14 12:05:00 UTC');

        (new RequestEventDelivery($transport))->publish($event->id);

        $this->assertSame([
            [
                'channels' => ['users.41', 'departments.7'],
                'event' => [
                    'event_id' => '11111111-1111-4111-8111-111111111111',
                    'event_name' => 'request.created',
                    'schema_version' => 1,
                    'request_id' => $event->request_id,
                    'aggregate_sequence' => 1,
                    'occurred_at' => '2026-10-14T12:00:00Z',
                    'payload' => ['request_id' => $event->request_id],
                ],
            ],
        ], $transport->publications);

        $event->refresh();

        $this->assertSame('published', $event->delivery_status);
        $this->assertSame(1, $event->attempt_count);
        $this->assertSame('2026-10-14 12:05:00', $event->last_attempt_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-14 12:05:00', $event->published_at->format('Y-m-d H:i:s'));
        $this->assertNull($event->last_error);
    }

    public function test_transport_failure_is_audited_and_rethrown(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00 UTC');

        $event = $this->makePendingEvent();
        $failure = new RuntimeException('transport unavailable');
        $transport = new FailingRequestEventTransport($failure);

        Carbon::setTestNow('2026-10-14 12:05:00 UTC');

        try {
            (new RequestEventDelivery($transport))->publish($event->id);
            $this->fail('Expected the transport failure to be rethrown.');
        } catch (RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }

        $event->refresh();

        $this->assertCount(1, $transport->publications);
        $this->assertSame('pending', $event->delivery_status);
        $this->assertSame(1, $event->attempt_count);
        $this->assertSame('2026-10-14 12:05:00', $event->last_attempt_at->format('Y-m-d H:i:s'));
        $this->assertNull($event->published_at);
        $this->assertSame('RuntimeException: transport unavailable', $event->last_error);
    }

    public function test_transport_failure_audit_is_normalized_and_bounded(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00 UTC');

        $event = $this->makePendingEvent();
        $failure = new RuntimeException("transport\n".str_repeat('x', 2000));

        try {
            (new RequestEventDelivery(new FailingRequestEventTransport($failure)))->publish($event->id);
            $this->fail('Expected the transport failure to be rethrown.');
        } catch (RuntimeException $caught) {
            $this->assertSame($failure, $caught);
        }

        $event->refresh();

        $this->assertLessThanOrEqual(1000, strlen($event->last_error));
        $this->assertStringStartsWith('RuntimeException: transport ', $event->last_error);
        $this->assertStringNotContainsString("\n", $event->last_error);
    }

    public function test_publishing_an_already_published_event_is_a_no_op(): void
    {
        Carbon::setTestNow('2026-10-14 12:00:00 UTC');

        $event = $this->makePendingEvent();
        $transport = new RecordingRequestEventTransport;
        $delivery = new RequestEventDelivery($transport);

        Carbon::setTestNow('2026-10-14 12:05:00 UTC');
        $delivery->publish($event->id);
        $event->refresh();

        $firstAttemptAt = $event->last_attempt_at->format('Y-m-d H:i:s');
        $firstPublishedAt = $event->published_at->format('Y-m-d H:i:s');

        Carbon::setTestNow('2026-10-14 12:10:00 UTC');
        $delivery->publish($event->id);
        $event->refresh();

        $this->assertCount(1, $transport->publications);
        $this->assertSame('published', $event->delivery_status);
        $this->assertSame(1, $event->attempt_count);
        $this->assertSame($firstAttemptAt, $event->last_attempt_at->format('Y-m-d H:i:s'));
        $this->assertSame($firstPublishedAt, $event->published_at->format('Y-m-d H:i:s'));
        $this->assertNull($event->last_error);
    }

    private function makePendingEvent(): RequestEvent
    {
        $student = User::create([
            'name' => 'Student One',
            'email' => 'student-one@example.test',
            'password' => 'password',
            'role' => 'student',
        ]);

        $requestType = RequestType::create([
            'name' => 'Transcript',
            'description' => 'Official academic transcript.',
            'default_department_sequence' => [],
        ]);

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'request_type_id' => $requestType->id,
            'status' => 'pending',
            'is_reopened' => false,
        ]);

        return RequestEvent::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'request_id' => $request->id,
            'event_name' => 'request.created',
            'schema_version' => 1,
            'aggregate_sequence' => 1,
            'delivery_status' => 'pending',
            'payload' => ['request_id' => $request->id],
            'audiences' => [
                'users' => [41, 41],
                'departments' => [7, 7],
            ],
        ]);
    }
}

class RecordingRequestEventTransport implements RequestEventTransport
{
    /** @var list<array{channels: list<string>, event: array<string, mixed>}> */
    public array $publications = [];

    public function publish(array $channels, array $event): void
    {
        $this->publications[] = compact('channels', 'event');
    }
}

class FailingRequestEventTransport implements RequestEventTransport
{
    /** @var list<array{channels: list<string>, event: array<string, mixed>}> */
    public array $publications = [];

    public function __construct(
        private readonly Throwable $failure,
    ) {}

    public function publish(array $channels, array $event): void
    {
        $this->publications[] = compact('channels', 'event');

        throw $this->failure;
    }
}
