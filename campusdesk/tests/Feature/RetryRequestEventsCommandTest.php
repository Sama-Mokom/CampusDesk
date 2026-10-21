<?php

namespace Tests\Feature;

use App\Jobs\PublishRequestEvent;
use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryRequestEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrying_a_specific_failed_event_resets_it_and_dispatches_job(): void
    {
        Queue::fake();

        $event = $this->makeEvent('failed');
        $event->update([
            'failed_at' => now(),
            'last_error' => 'Connection timeout',
        ]);

        $this->artisan('realtime:retry', ['eventId' => $event->id])
            ->assertSuccessful()
            ->expectsOutputToContain("Reset event [{$event->id}] to pending and queued for retry.");

        $event->refresh();

        $this->assertSame('pending', $event->delivery_status);
        $this->assertNull($event->failed_at);

        Queue::assertPushedOn(
            'realtime',
            PublishRequestEvent::class,
            fn (PublishRequestEvent $job): bool => $job->eventId === $event->id,
        );
    }

    public function test_retrying_all_failed_events_resets_them_and_dispatches_jobs(): void
    {
        Queue::fake();

        $failedEvent1 = $this->makeEvent('failed', '33333333-3333-4333-8333-333333333331');
        $failedEvent2 = $this->makeEvent('failed', '33333333-3333-4333-8333-333333333332');
        $publishedEvent = $this->makeEvent('published', '33333333-3333-4333-8333-333333333333');

        $this->artisan('realtime:retry', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Retried 2 failed event(s).');

        $failedEvent1->refresh();
        $failedEvent2->refresh();
        $publishedEvent->refresh();

        $this->assertSame('pending', $failedEvent1->delivery_status);
        $this->assertSame('pending', $failedEvent2->delivery_status);
        $this->assertSame('published', $publishedEvent->delivery_status);

        Queue::assertPushed(PublishRequestEvent::class, 2);
    }

    public function test_retrying_a_non_failed_event_is_rejected(): void
    {
        Queue::fake();

        $event = $this->makeEvent('published');

        $this->artisan('realtime:retry', ['eventId' => $event->id])
            ->assertFailed()
            ->expectsOutputToContain("Event [{$event->id}] has delivery status [published]; only failed events can be retried.");

        Queue::assertNothingPushed();
    }

    public function test_retrying_without_arguments_or_all_flag_fails(): void
    {
        Queue::fake();

        $this->artisan('realtime:retry')
            ->assertFailed()
            ->expectsOutputToContain('Please provide an eventId or use the --all flag.');

        Queue::assertNothingPushed();
    }

    private function makeEvent(string $deliveryStatus = 'pending', string $id = '33333333-3333-4333-8333-333333333330'): RequestEvent
    {
        $student = User::create([
            'name' => "Student Retry {$id}",
            'email' => "student-retry-{$id}@example.test",
            'password' => 'password',
            'role' => 'student',
        ]);

        $requestType = RequestType::create([
            'name' => "Transcript Retry {$id}",
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
            'id' => $id,
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
