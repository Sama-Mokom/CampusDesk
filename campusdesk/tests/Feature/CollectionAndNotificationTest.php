<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Request as DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectionAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
    }

    private function readyRequest(User $student): DocumentRequest
    {
        $typeId = DB::table('request_types')->insertGetId([
            'name' => 'Collection test', 'description' => 'Test',
            'default_department_sequence' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DocumentRequest::create([
            'student_id' => $student->id, 'request_type_id' => $typeId,
            'description' => 'Ready request', 'status' => 'ready', 'is_reopened' => false,
        ]);
    }

    public function test_student_can_mark_own_ready_request_as_collected(): void
    {
        $student = $this->student();
        $request = $this->readyRequest($student);

        $this->actingAs($student, 'sanctum')->patchJson("/api/requests/{$request->id}/collect")
            ->assertOk()->assertJsonPath('data.status', 'collected');

        $this->assertDatabaseHas('status_history', [
            'request_id' => $request->id, 'old_status' => 'ready', 'new_status' => 'collected',
            'changed_by' => $student->id, 'request_stage_id' => null,
        ]);
    }

    public function test_collection_requires_owner_and_ready_status(): void
    {
        $owner = $this->student();
        $other = $this->student();
        $request = $this->readyRequest($owner);

        $this->actingAs($other, 'sanctum')->patchJson("/api/requests/{$request->id}/collect")->assertForbidden();
        $request->update(['status' => 'pending']);
        $this->actingAs($owner, 'sanctum')->patchJson("/api/requests/{$request->id}/collect")
            ->assertUnprocessable();
    }

    public function test_notifications_are_scoped_to_user_and_can_be_marked_read(): void
    {
        $owner = $this->student();
        $other = $this->student();
        $unread = Notification::create(['user_id' => $owner->id, 'type' => 'request_status_updated', 'message' => 'Unread']);
        Notification::create(['user_id' => $other->id, 'type' => 'request_status_updated', 'message' => 'Private']);

        $this->actingAs($owner, 'sanctum')->getJson('/api/notifications')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unread->id);

        $this->actingAs($other, 'sanctum')->patchJson("/api/notifications/{$unread->id}/read")->assertForbidden();
        $this->actingAs($owner, 'sanctum')->patchJson("/api/notifications/{$unread->id}/read")
            ->assertOk()->assertJsonPath('data.read', true);
        $this->assertDatabaseHas('notifications', ['id' => $unread->id, 'read' => true]);
    }
}
