<?php

namespace App\Services;

use App\Contracts\RequestEventTransport;
use App\Models\RequestEvent;
use Throwable;

class RequestEventDelivery
{
    private const ERROR_SUMMARY_MAX_BYTES = 1000;

    public function __construct(
        private readonly RequestEventTransport $transport,
    ) {}

    public function publish(string $eventId): void
    {
        if (! config('realtime.delivery_enabled', true)) {
            return;
        }

        $event = RequestEvent::query()->findOrFail($eventId);

        if ($event->delivery_status === 'published') {
            return;
        }

        $event->update([
            'attempt_count' => $event->attempt_count + 1,
            'last_attempt_at' => now(),
        ]);

        try {
            $this->transport->publish(
                $this->channelsFor($event),
                [
                    'event_id' => $event->id,
                    'event_name' => $event->event_name,
                    'schema_version' => $event->schema_version,
                    'request_id' => $event->request_id,
                    'aggregate_sequence' => $event->aggregate_sequence,
                    'occurred_at' => $event->created_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'payload' => $event->payload,
                ],
            );
        } catch (Throwable $failure) {
            $event->update([
                'last_error' => self::summarizeError($failure),
            ]);

            throw $failure;
        }

        $event->update([
            'delivery_status' => 'published',
            'published_at' => now(),
            'last_error' => null,
        ]);
    }

    /** @return list<string> */
    private function channelsFor(RequestEvent $event): array
    {
        $channels = [];

        foreach ($event->audiences['users'] ?? [] as $userId) {
            $channels[] = "users.{$userId}";
        }

        foreach ($event->audiences['departments'] ?? [] as $departmentId) {
            $channels[] = "departments.{$departmentId}";
        }

        return array_values(array_unique($channels));
    }

    public static function summarizeError(Throwable $failure): string
    {
        $message = preg_replace('/[\x00-\x20\x7F]+/', ' ', $failure->getMessage()) ?? '';
        $summary = $failure::class.': '.trim($message);

        return mb_strcut($summary, 0, self::ERROR_SUMMARY_MAX_BYTES, 'UTF-8');
    }
}
