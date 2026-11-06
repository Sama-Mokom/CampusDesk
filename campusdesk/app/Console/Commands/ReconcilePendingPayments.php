<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile-pending
                            {--dry-run : Inspect payments without committing changes}
                            {--limit=50 : Max number of pending payments to reconcile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile pending payments older than 90 seconds with the gateway API';

    /**
     * Execute the console command.
     */
    public function handle(
        PaymentService $paymentService,
        PaymentGatewayManager $gatewayManager
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $pendingPayments = Payment::query()
            ->whereIn('status', ['pending', 'processing'])
            ->where('initiated_at', '<=', now()->subSeconds(90))
            ->where('initiated_at', '>=', now()->subHours(24))
            ->orderBy('initiated_at', 'asc')
            ->limit($limit)
            ->get();

        $this->info("Found {$pendingPayments->count()} pending payment(s) to reconcile.");

        $successful = 0;
        $failed = 0;
        $stillPending = 0;

        foreach ($pendingPayments as $payment) {
            $this->line("Checking Payment #{$payment->id} ({$payment->external_id}, Provider: {$payment->provider})...");

            if ($dryRun) {
                $this->comment("  [DRY RUN] Skipped sync.");
                continue;
            }

            try {
                // If payment has passed expiration timestamp and still pending, attempt provider expiry
                if ($payment->expires_at && $payment->expires_at <= now()) {
                    try {
                        $gatewayManager->driver($payment->provider)->expirePayment($payment);
                    } catch (\Throwable $e) {
                        Log::channel('payments')->warning("Failed to call expire on gateway for #{$payment->id}: " . $e->getMessage());
                    }
                }

                $synced = $paymentService->syncStatus($payment);

                match ($synced->status) {
                    'successful' => $successful++,
                    'failed', 'expired', 'cancelled' => $failed++,
                    default => $stillPending++,
                };

                $this->info("  -> Status is now: {$synced->status}");
            } catch (\Throwable $e) {
                $this->error("  -> Error: {$e->getMessage()}");
                Log::channel('payments')->error("Reconciliation error for payment #{$payment->id}: " . $e->getMessage(), [
                    'payment_id' => $payment->id,
                    'exception'  => $e,
                ]);
            }
        }

        $this->newLine();
        $this->table(
            ['Total Inspected', 'Successful', 'Failed / Expired', 'Still Pending'],
            [[$pendingPayments->count(), $successful, $failed, $stillPending]]
        );

        return self::SUCCESS;
    }
}
