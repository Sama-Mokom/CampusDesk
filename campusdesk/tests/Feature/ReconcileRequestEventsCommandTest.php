<?php

namespace Tests\Feature;

use App\Jobs\PublishRequestEvent;
use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconcileRequestEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_command_dispatches_pending_events_only(): void
    {
        Queue::fake();

        $pending1 = $this->makeEvent('pending', '44444444-4444-4444-8444-444444444441');
        $pending2 = $this->makeEvent('pending', '44444444-4444-4444-8444-444444444442');
        $published = $this->makeEvent('published', '44444444-4444-4444-8444-444444444443');
        $failed = $this->makeEvent('failed', '44444444-4444-4444-8444-444444444444');

        $this->artisan('realtime:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('Reconciled 2 pending event(s).');

        Queue::assertPushedOn(
            'realtime',
            PublishRequestEvent::class,
            fn (PublishRequestEvent $job): bool => $job->eventId === $pending1->id,
        );

        Queue::assertPushedOn(
            'realtime',
            PublishRequestEvent::class,
            fn (PublishRequestEvent $job): bool => $job->eventId === $pending2->id,
        );

        Queue::assertPushed(PublishRequestEvent::class, 2);
    }

    public function test_reconcile_command_outputs_when_no_pending_events(): void
    {
        Queue::fake();

        $this->artisan('realtime:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('No pending events to reconcile.');

        Queue::assertNothingPushed();
    }

    public function test_reconcile_command_is_scheduled_every_minute_without_overlapping(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events())->filter(function ($event) {
            return str_contains($event->command ?? '', 'realtime:reconcile');
        });

        $this->assertCount(1, $events, 'Expected realtime:reconcile to be scheduled.');

        $scheduledEvent = $events->first();
        $this->assertSame('* * * * *', $scheduledEvent->expression);
        $this->assertTrue($scheduledEvent->withoutOverlapping);
    }

    private function makeEvent(string $deliveryStatus = 'pending', string $id = '44444444-4444-4444-8444-444444444440'): RequestEvent
    {
        $student = User::create([
            'name' => "Student Reconcile {$id}",
            'email' => "student-rec-{$id}@example.test",
            'password' => 'password',
            'role' => 'student',
        ]);

        $requestType = RequestType::create([
            'name' => "Transcript Reconcile {$id}",
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
