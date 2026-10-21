<?php

namespace Tests\Feature;

use App\Contracts\RequestEventTransport;
use App\Jobs\PublishRequestEvent;
use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestType;
use App\Models\User;
use App\Services\RequestEventDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RealtimeRuntimeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_is_skipped_when_feature_flag_is_disabled(): void
    {
        config(['realtime.delivery_enabled' => false]);

        $event = $this->makeEvent();
        $mockTransport = Mockery::mock(RequestEventTransport::class);
        $mockTransport->shouldNotReceive('publish');

        $delivery = new RequestEventDelivery($mockTransport);
        $delivery->publish($event->id);

        $event->refresh();

        $this->assertSame('pending', $event->delivery_status);
        $this->assertSame(0, $event->attempt_count);
        $this->assertNull($event->published_at);
        $this->assertNull($event->last_attempt_at);
    }

    public function test_reconciliation_is_skipped_when_feature_flag_is_disabled(): void
    {
        Queue::fake();
        config(['realtime.delivery_enabled' => false]);

        $this->makeEvent();

        $this->artisan('realtime:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('Realtime delivery is currently disabled by feature flag.');

        Queue::assertNothingPushed();
    }

    public function test_delivery_proceeds_when_feature_flag_is_enabled(): void
    {
        config(['realtime.delivery_enabled' => true]);

        $event = $this->makeEvent();
        $mockTransport = Mockery::mock(RequestEventTransport::class);
        $mockTransport->shouldReceive('publish')->once();

        $delivery = new RequestEventDelivery($mockTransport);
        $delivery->publish($event->id);

        $event->refresh();

        $this->assertSame('published', $event->delivery_status);
        $this->assertSame(1, $event->attempt_count);
        $this->assertNotNull($event->published_at);
    }

    public function test_realtime_config_file_defines_expected_keys(): void
    {
        $this->assertNotNull(config('realtime.delivery_enabled'));
        $this->assertNotNull(config('realtime.replay_historical_backlog'));
    }

    private function makeEvent(): RequestEvent
    {
        $student = User::create([
            'name' => 'Student Runtime',
            'email' => 'student-runtime@example.test',
            'password' => 'password',
            'role' => 'student',
        ]);

        $requestType = RequestType::create([
            'name' => 'Transcript Runtime',
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
            'id' => '55555555-5555-4555-8555-555555555555',
            'request_id' => $request->id,
            'event_name' => 'request.created',
            'schema_version' => 1,
            'aggregate_sequence' => 1,
            'delivery_status' => 'pending',
            'payload' => ['request_id' => $request->id],
            'audiences' => ['users' => [$student->id]],
        ]);
    }
}
