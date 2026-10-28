<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Payment;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_payment_webhook_syncs_status_and_marks_request_pending(): void
    {
        $faculty = Faculty::create(['name' => 'Faculty of Law', 'code' => 'FL', 'matricule_prefix' => 'LAW']);
        $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Public Law', 'code' => 'PLAW', 'type' => 'academic']);
        $prog = Programme::create(['department_id' => $dept->id, 'name' => 'LLB', 'code' => 'LLB', 'degree_type' => 'BACHELOR']);

        $student = User::factory()->create(['role' => 'student']);
        $student->studentProfile->update(['faculty_id' => $faculty->id, 'department_id' => $dept->id, 'programme_id' => $prog->id, 'matricule' => 'LAW24001']);

        $type = RequestType::create(['name' => 'Law Attestation', 'default_department_sequence' => [$dept->id]]);
        $tier = RequestTypeTier::create([
            'request_type_id' => $type->id,
            'code' => 'standard',
            'name' => 'Standard',
            'fee_amount' => 2000,
            'turnaround_days' => 3,
            'priority' => 2,
            'is_active' => true,
        ]);

        $createRes = $this->actingAs($student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $type->id,
            'request_type_tier_id' => $tier->id,
        ]);
        $requestId = $createRes->json('data.id');

        $payRes = $this->actingAs($student, 'sanctum')->postJson("/api/requests/{$requestId}/payments", [
            'checkout_mode' => 'hosted',
        ]);
        $externalId = $payRes->json('data.external_id');

        // Gateway sends webhook
        $webhookRes = $this->postJson('/api/webhooks/payments/fake', [
            'external_id' => $externalId,
            'status' => 'successful',
        ]);

        $webhookRes->assertOk();
        $webhookRes->assertJsonPath('status', 'processed');
        $webhookRes->assertJsonPath('payment_status', 'successful');

        $this->assertDatabaseHas('requests', [
            'id' => $requestId,
            'status' => 'pending',
            'amount_paid' => 2000,
        ]);

        $this->assertDatabaseHas('payments', [
            'external_id' => $externalId,
            'status' => 'successful',
        ]);
    }
}
