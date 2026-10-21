<?php

namespace Tests\Feature;

use App\Contracts\RequestEventTransport;
use App\Services\BroadcastingRequestEventTransport;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Mockery;
use Tests\TestCase;

class BroadcastingRequestEventTransportTest extends TestCase
{
    public function test_container_binds_request_event_transport_to_broadcasting_adapter(): void
    {
        $transport = app(RequestEventTransport::class);

        $this->assertInstanceOf(BroadcastingRequestEventTransport::class, $transport);
    }

    public function test_publish_broadcasts_to_private_channels_synchronously_with_generic_wire_event(): void
    {
        $mockBroadcaster = Mockery::mock(Broadcaster::class);

        $eventPayload = [
            'event_id' => '11111111-1111-4111-8111-111111111111',
            'event_name' => 'request.created',
            'schema_version' => 1,
            'request_id' => 42,
            'aggregate_sequence' => 1,
            'occurred_at' => '2026-10-16T12:00:00Z',
            'payload' => ['request_id' => 42],
        ];

        $mockBroadcaster->shouldReceive('broadcast')
            ->once()
            ->withArgs(function (array $channels, string $eventName, array $payload) use ($eventPayload): bool {
                if ($eventName !== 'request.event') {
                    return false;
                }

                if ($payload !== $eventPayload) {
                    return false;
                }

                if (count($channels) !== 2) {
                    return false;
                }

                $channel1 = $channels[0];
                $channel2 = $channels[1];

                return $channel1 instanceof PrivateChannel
                    && (string) $channel1 === 'private-users.41'
                    && $channel2 instanceof PrivateChannel
                    && (string) $channel2 === 'private-departments.7';
            });

        $transport = new BroadcastingRequestEventTransport($mockBroadcaster);
        $transport->publish(['users.41', 'departments.7'], $eventPayload);
    }

    public function test_publish_does_nothing_when_channels_are_empty(): void
    {
        $mockBroadcaster = Mockery::mock(Broadcaster::class);
        $mockBroadcaster->shouldNotReceive('broadcast');

        $transport = new BroadcastingRequestEventTransport($mockBroadcaster);
        $transport->publish([], [
            'event_id' => '11111111-1111-4111-8111-111111111111',
            'event_name' => 'request.created',
            'schema_version' => 1,
            'request_id' => 42,
            'aggregate_sequence' => 1,
            'occurred_at' => '2026-10-16T12:00:00Z',
            'payload' => ['request_id' => 42],
        ]);
    }
}
