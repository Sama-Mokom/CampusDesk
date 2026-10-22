<?php

namespace App\Services;

use App\Contracts\RequestEventTransport;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;

class BroadcastingRequestEventTransport implements RequestEventTransport
{
    public function __construct(
        private readonly Broadcaster $broadcaster,
    ) {}

    public function publish(array $channels, array $event): void
    {
        if (empty($channels)) {
            return;
        }

        $privateChannels = array_map(
            fn (string $channel): PrivateChannel => new PrivateChannel($channel),
            $channels,
        );

        $this->broadcaster->broadcast($privateChannels, 'request.event', $event);
    }
}
