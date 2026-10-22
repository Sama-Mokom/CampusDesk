<?php

namespace App\Console\Commands;

use App\Jobs\PublishRequestEvent;
use App\Models\RequestEvent;
use Illuminate\Console\Command;

class RetryFailedRequestEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'realtime:retry
                            {eventId? : The UUID of a specific failed event}
                            {--all : Retry all failed events}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset failed request events to pending and re-enqueue them for realtime delivery';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $eventId = $this->argument('eventId');
        $all = (bool) $this->option('all');

        if (! $eventId && ! $all) {
            $this->error('Please provide an eventId or use the --all flag.');

            return self::FAILURE;
        }

        if ($eventId) {
            $event = RequestEvent::query()->find($eventId);

            if (! $event) {
                $this->error("Event [{$eventId}] not found.");

                return self::FAILURE;
            }

            if ($event->delivery_status !== 'failed') {
                $this->error("Event [{$event->id}] has delivery status [{$event->delivery_status}]; only failed events can be retried.");

                return self::FAILURE;
            }

            $event->update([
                'delivery_status' => 'pending',
                'failed_at' => null,
            ]);

            PublishRequestEvent::dispatch($event->id);

            $this->info("Reset event [{$event->id}] to pending and queued for retry.");

            return self::SUCCESS;
        }

        $failedEvents = RequestEvent::query()
            ->where('delivery_status', 'failed')
            ->get();

        if ($failedEvents->isEmpty()) {
            $this->info('No failed events to retry.');

            return self::SUCCESS;
        }

        foreach ($failedEvents as $event) {
            $event->update([
                'delivery_status' => 'pending',
                'failed_at' => null,
            ]);

            PublishRequestEvent::dispatch($event->id);
        }

        $this->info("Retried {$failedEvents->count()} failed event(s).");

        return self::SUCCESS;
    }
}
