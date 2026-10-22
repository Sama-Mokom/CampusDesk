<?php

namespace App\Console\Commands;

use App\Jobs\PublishRequestEvent;
use App\Models\RequestEvent;
use Illuminate\Console\Command;

class ReconcileRequestEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'realtime:reconcile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find pending request events and enqueue delivery jobs as a durability backstop';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('realtime.delivery_enabled', true)) {
            $this->info('Realtime delivery is currently disabled by feature flag.');

            return self::SUCCESS;
        }

        $pendingEvents = RequestEvent::query()
            ->where('delivery_status', 'pending')
            ->orderBy('created_at')
            ->get();

        if ($pendingEvents->isEmpty()) {
            $this->info('No pending events to reconcile.');

            return self::SUCCESS;
        }

        foreach ($pendingEvents as $event) {
            PublishRequestEvent::dispatch($event->id);
        }

        $this->info("Reconciled {$pendingEvents->count()} pending event(s).");

        return self::SUCCESS;
    }
}
