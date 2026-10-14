<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\RequestEvent;
use App\Models\RequestType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestEventOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_submission_records_one_pending_request_created_event(): void
    {
        [$student, $requestType, $department] = $this->makeStudentAndRequestType();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/requests', [
                'request_type_id' => $requestType->id,
                'description' => 'Private student context must not enter the event payload.',
            ])
            ->assertCreated();

        $requestId = (int) $response->json('data.id');
        $event = RequestEvent::query()->sole();

        $this->assertTrue(Str::isUuid($event->id));
        $this->assertSame([
            'request_id' => $requestId,
            'event_name' => 'request.created',
            'schema_version' => 1,
            'aggregate_sequence' => 1,
            'delivery_status' => 'pending',
            'payload' => ['request_id' => $requestId],
            'audiences' => [
                'users' => [$student->id],
                'departments' => [$department->id],
            ],
        ], [
            'request_id' => $event->request_id,
            'event_name' => $event->event_name,
            'schema_version' => $event->schema_version,
            'aggregate_sequence' => $event->aggregate_sequence,
            'delivery_status' => $event->delivery_status,
            'payload' => $event->payload,
            'audiences' => $event->audiences,
        ]);
    }

    /**
     * @return array{0: User, 1: RequestType, 2: Department}
     */
    private function makeStudentAndRequestType(): array
    {
        $faculty = Faculty::create([
            'name' => 'Faculty of Science',
            'code' => 'SCI',
            'matricule_prefix' => 'SCI',
        ]);

        $department = Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Computer Science',
            'code' => 'CSC',
            'type' => 'academic',
        ]);

        $programme = Programme::create([
            'department_id' => $department->id,
            'name' => 'Computer Science',
            'code' => 'BSC-CS',
            'degree_type' => 'BACHELOR',
        ]);

        $student = User::create([
            'name' => 'Student One',
            'email' => 'student-one@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'student',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'matricule' => 'SCI0001',
            'level' => '100',
            'status' => 'active',
        ]);

        $requestType = RequestType::create([
            'name' => 'Transcript',
            'description' => 'Official academic transcript.',
            'default_department_sequence' => [$department->id],
        ]);

        return [$student, $requestType, $department];
    }
}
