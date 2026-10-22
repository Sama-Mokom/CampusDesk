<?php

namespace App\Jobs;

use App\Models\RequestEvent;
use App\Services\RequestEventDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishRequestEvent implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $eventId,
    ) {
        $this->onConnection('database');
        $this->onQueue('realtime');
    }

    public function uniqueId(): string
    {
        return $this->eventId;
    }

    public function backoff(): array
    {
        return [1, 5, 30, 120, 600];
    }

    public function handle(RequestEventDelivery $delivery): void
    {
        $delivery->publish($this->eventId);
    }

    public function failed(?Throwable $exception): void
    {
        $event = RequestEvent::query()->find($this->eventId);

        if ($event === null || $event->delivery_status === 'published') {
            return;
        }

        $updates = [
            'delivery_status' => 'failed',
            'failed_at' => now(),
        ];

        if ($exception !== null && empty($event->last_error)) {
            $updates['last_error'] = RequestEventDelivery::summarizeError($exception);
        }

        $event->update($updates);
    }
}
