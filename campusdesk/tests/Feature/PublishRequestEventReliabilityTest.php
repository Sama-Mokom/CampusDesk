<?php

namespace Tests\Feature;

use App\Jobs\PublishRequestEvent;
use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class PublishRequestEventReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_implements_should_be_unique(): void
    {
        $job = new PublishRequestEvent('test-event-uuid');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('test-event-uuid', $job->uniqueId());
        $this->assertGreaterThanOrEqual(756, $job->uniqueFor);
    }

    public function test_job_specifies_retry_and_backoff_configuration(): void
    {
        $job = new PublishRequestEvent('test-event-uuid');

        $this->assertSame(6, $job->tries);
        $this->assertSame([1, 5, 30, 120, 600], $job->backoff());
        $this->assertSame(30, $job->timeout);
    }

    public function test_exhausted_job_marks_outbox_event_as_failed(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00 UTC');

        $event = $this->makeEvent('pending');
        $job = new PublishRequestEvent($event->id);

        $failure = new RuntimeException('Repeated transport timeout');
        $job->failed($failure);

        $event->refresh();

        $this->assertSame('failed', $event->delivery_status);
        $this->assertNotNull($event->failed_at);
        $this->assertSame('2026-10-16 10:00:00', $event->failed_at->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Repeated transport timeout', (string) $event->last_error);
    }

    public function test_failed_callback_does_not_overwrite_already_published_event(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00 UTC');

        $event = $this->makeEvent('published');
        $event->update(['published_at' => now()]);

        $job = new PublishRequestEvent($event->id);
        $job->failed(new RuntimeException('Late failure after publish'));

        $event->refresh();

        $this->assertSame('published', $event->delivery_status);
        $this->assertNull($event->failed_at);
    }

    private function makeEvent(string $deliveryStatus = 'pending'): RequestEvent
    {
        $student = User::create([
            'name' => 'Student Reliability',
            'email' => 'student-rel@example.test',
            'password' => 'password',
            'role' => 'student',
        ]);

        $requestType = RequestType::create([
            'name' => 'Transcript Reliability',
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
            'id' => '22222222-2222-4222-8222-222222222222',
            'request_id' => $request->id,
            'event_name' => 'request.created',
            'schema_version' => 1,
            'aggregate_sequence' => 1,
            'delivery_status' => $deliveryStatus,
            'payload' => ['request_id' => $request->id],
            'audiences' => ['users' => [$student->id]],
        ]);
    }
}
