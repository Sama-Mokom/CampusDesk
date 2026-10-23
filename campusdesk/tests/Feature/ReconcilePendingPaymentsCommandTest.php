<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Payment;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconcilePendingPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Faculty $faculty;
    protected Department $department;
    protected Programme $programme;
    protected User $student;
    protected RequestType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty = Faculty::create([
            'name'             => 'Faculty of Science',
            'code'             => 'FS',
            'matricule_prefix' => 'SC',
        ]);

        $this->department = Department::create([
            'faculty_id' => $this->faculty->id,
            'name'       => 'Computer Science',
            'code'       => 'CSC',
            'type'       => 'academic',
        ]);

        $this->programme = Programme::create([
            'department_id' => $this->department->id,
            'name'          => 'BSc Computer Science',
            'code'          => 'BSCCSC',
            'degree_type'   => 'BACHELOR',
        ]);

        $this->student = User::factory()->create(['role' => 'student']);

        $this->type = RequestType::create([
            'name'                        => 'Transcript',
            'default_department_sequence' => [$this->department->id],
        ]);

        Config::set('payments.default', 'fapshi');
        Config::set('payments.gateways.fapshi.api_user', 'fapshi-user');
        Config::set('payments.gateways.fapshi.api_key', 'fapshi-key');
        Config::set('payments.gateways.fapshi.base_url', 'https://sandbox.fapshi.com');
    }

    public function test_reconcile_command_skips_recent_payments_and_syncs_eligible_payments(): void
    {
        $tier = $this->type->tiers()->create([
            'code'            => 'FAST',
            'name'            => 'Fast Mode',
            'fee_amount'      => 2500,
            'currency'        => 'XAF',
            'turnaround_days' => 7,
            'priority'        => 2,
            'is_active'       => true,
        ]);

        $request = DocumentRequest::create([
            'student_id'              => $this->student->id,
            'request_type_id'         => $this->type->id,
            'request_type_tier_id'    => $tier->id,
            'tier_name_snapshot'      => $tier->name,
            'fee_amount'              => $tier->fee_amount,
            'amount_paid'             => 0,
            'currency'                => 'XAF',
            'turnaround_days_snapshot'=> 7,
            'tier_priority'           => 2,
            'status'                  => 'awaiting_payment',
            'description'             => 'Awaiting fee payment',
            'is_reopened'             => false,
        ]);

        // 1. Recent payment (< 90 seconds ago) -> should NOT be picked up
        $recentPayment = Payment::create([
            'request_id'              => $request->id,
            'request_type_tier_id'    => $tier->id,
            'external_id'             => 'PAY_RECENT_01',
            'provider'                => 'fapshi',
            'provider_transaction_id' => 'TRANS_RECENT_01',
            'payment_type'            => 'initial',
            'checkout_mode'           => 'hosted',
            'amount'                  => 2500,
            'currency'                => 'XAF',
            'status'                  => 'pending',
            'initiated_at'            => now()->subSeconds(30),
        ]);

        // 2. Eligible payment (5 minutes ago) -> should be picked up and reconciled
        $eligiblePayment = Payment::create([
            'request_id'              => $request->id,
            'request_type_tier_id'    => $tier->id,
            'external_id'             => 'PAY_ELIGIBLE_02',
            'provider'                => 'fapshi',
            'provider_transaction_id' => 'TRANS_ELIGIBLE_02',
            'payment_type'            => 'initial',
            'checkout_mode'           => 'hosted',
            'amount'                  => 2500,
            'currency'                => 'XAF',
            'status'                  => 'pending',
            'initiated_at'            => now()->subMinutes(5),
        ]);

        Http::fake([
            'https://sandbox.fapshi.com/payment-status/TRANS_ELIGIBLE_02' => Http::response([
                'status'  => 'SUCCESSFUL',
                'amount'  => 2500,
                'transId' => 'TRANS_ELIGIBLE_02',
            ], 200),
        ]);

        // Run reconciliation command
        $this->artisan('payments:reconcile-pending')
            ->expectsOutputToContain('Found 1 pending payment(s) to reconcile.')
            ->expectsOutputToContain('PAY_ELIGIBLE_02')
            ->assertSuccessful();

        // Recent payment remains pending
        $recentPayment->refresh();
        $this->assertSame('pending', $recentPayment->status);

        // Eligible payment is transitioned to successful
        $eligiblePayment->refresh();
        $this->assertSame('successful', $eligiblePayment->status);

        // Request transitioned to pending
        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame(2500, $request->amount_paid);
    }

    public function test_reconcile_command_dry_run_does_not_modify_state(): void
    {
        $tier = $this->type->tiers()->create([
            'code'            => 'NORMAL',
            'name'            => 'Normal Mode',
            'fee_amount'      => 1000,
            'currency'        => 'XAF',
            'turnaround_days' => 14,
            'priority'        => 1,
            'is_active'       => true,
        ]);

        $request = DocumentRequest::create([
            'student_id'              => $this->student->id,
            'request_type_id'         => $this->type->id,
            'request_type_tier_id'    => $tier->id,
            'tier_name_snapshot'      => $tier->name,
            'fee_amount'              => $tier->fee_amount,
            'amount_paid'             => 0,
            'currency'                => 'XAF',
            'turnaround_days_snapshot'=> 14,
            'tier_priority'           => 1,
            'status'                  => 'awaiting_payment',
            'description'             => 'Awaiting fee payment',
            'is_reopened'             => false,
        ]);

        $payment = Payment::create([
            'request_id'              => $request->id,
            'request_type_tier_id'    => $tier->id,
            'external_id'             => 'PAY_DRY_01',
            'provider'                => 'fapshi',
            'provider_transaction_id' => 'TRANS_DRY_01',
            'payment_type'            => 'initial',
            'checkout_mode'           => 'hosted',
            'amount'                  => 1000,
            'currency'                => 'XAF',
            'status'                  => 'pending',
            'initiated_at'            => now()->subMinutes(10),
        ]);

        $this->artisan('payments:reconcile-pending', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN] Skipped sync.')
            ->assertSuccessful();

        $payment->refresh();
        $this->assertSame('pending', $payment->status);
    }
}
