<?php

namespace Tests\Feature;

use App\Contracts\RequestEventTransport;
use App\Jobs\PublishRequestEvent;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestEvent;
use App\Models\RequestType;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\RequestEventDelivery;
use App\Services\RequestEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishRequestEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_committed_request_creation_queues_its_event_for_realtime_delivery(): void
    {
        Queue::fake();
        [$student, $requestType] = $this->makeStudentAndRequestType('committed');

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/requests', [
                'request_type_id' => $requestType->id,
            ])
            ->assertCreated();

        $event = RequestEvent::query()->sole();

        Queue::assertPushedOn(
            'realtime',
            PublishRequestEvent::class,
            fn (PublishRequestEvent $job): bool => $job->eventId === $event->id
                && $job->connection === 'database',
        );
        Queue::assertPushed(PublishRequestEvent::class, 1);
    }

    public function test_rolled_back_request_creation_queues_nothing(): void
    {
        Queue::fake();
        [$student, $requestType] = $this->makeStudentAndRequestType('rolled-back');

        $this->actingAs($student, 'sanctum')
            ->post('/api/requests', [
                'request_type_id' => $requestType->id,
                'attachments' => [new QueueSliceStoreFailureUploadedFile],
            ])
            ->assertServerError();

        $this->assertDatabaseCount('request_events', 0);
        Queue::assertNotPushed(PublishRequestEvent::class);
    }

    public function test_job_publishes_the_recorded_event_through_the_delivery_service(): void
    {
        Queue::fake();
        [$student, $requestType] = $this->makeStudentAndRequestType('job');

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/requests', [
                'request_type_id' => $requestType->id,
            ])
            ->assertCreated();

        $event = RequestEvent::query()->sole();
        $transport = new QueueSliceRecordingRequestEventTransport;

        (new PublishRequestEvent($event->id))->handle(new RequestEventDelivery($transport));

        $this->assertSame([$event->id], $transport->eventIds);
        $this->assertSame('published', $event->refresh()->delivery_status);
    }

    /**
     * @return array{0: User, 1: RequestType}
     */
    private function makeStudentAndRequestType(string $suffix): array
    {
        $faculty = Faculty::create([
            'name' => "Faculty {$suffix}",
            'code' => "FAC-{$suffix}",
            'matricule_prefix' => "F{$suffix}",
        ]);

        $department = Department::create([
            'faculty_id' => $faculty->id,
            'name' => "Department {$suffix}",
            'code' => "DEP-{$suffix}",
            'type' => 'academic',
        ]);

        $programme = Programme::create([
            'department_id' => $department->id,
            'name' => "Programme {$suffix}",
            'code' => "PROG-{$suffix}",
            'degree_type' => 'BACHELOR',
        ]);

        $student = User::create([
            'name' => "Student {$suffix}",
            'email' => "{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'student',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'matricule' => "MAT-{$suffix}",
            'level' => '100',
            'status' => 'active',
        ]);

        $requestType = RequestType::create([
            'name' => "Request type {$suffix}",
            'description' => 'Queue slice request type.',
            'default_department_sequence' => [$department->id],
        ]);

        return [$student, $requestType];
    }

    public function test_committed_status_change_queues_its_event_for_realtime_delivery(): void
    {
        Queue::fake();
        [$student, $requestType, $department] = $this->makeStudentRequestTypeAndDepartment('status-change');

        $createResponse = $this->actingAs($student, 'sanctum')
            ->postJson('/api/requests', [
                'request_type_id' => $requestType->id,
            ])
            ->assertCreated();

        $requestId = (int) $createResponse->json('data.id');
        DocumentRequest::query()->whereKey($requestId)->update(['status' => 'ready']);

        $this->actingAs($student, 'sanctum')
            ->patchJson("/api/requests/{$requestId}/collect")
            ->assertOk();

        $statusChangedEvent = RequestEvent::query()
            ->where('event_name', 'request.status_changed')
            ->sole();

        Queue::assertPushedOn(
            'realtime',
            PublishRequestEvent::class,
            fn (PublishRequestEvent $job): bool => $job->eventId === $statusChangedEvent->id
                && $job->connection === 'database',
        );
    }

    public function test_committed_stage_claim_queues_its_event_for_realtime_delivery(): void
    {
        Queue::fake();
        [$student, $requestType, $department] = $this->makeStudentRequestTypeAndDepartment('stage-claim');

        $createResponse = $this->actingAs($student, 'sanctum')
            ->postJson('/api/requests', [
                'request_type_id' => $requestType->id,
            ])
            ->assertCreated();

        $requestId = (int) $createResponse->json('data.id');
        $stageId = (int) $createResponse->json('data.stages.0.id');
        $staff = $this->makeStaffInDepartment($department, 'stage-claim');

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/requests/{$requestId}/stages/{$stageId}/claim")
            ->assertOk();

        $stageClaimedEvent = RequestEvent::query()
            ->where('event_name', 'request_stage.claimed')
            ->sole();

        Queue::assertPushedOn(
            'realtime',
            PublishRequestEvent::class,
            fn (PublishRequestEvent $job): bool => $job->eventId === $stageClaimedEvent->id
                && $job->connection === 'database',
        );
    }

    public function test_dispatch_failure_after_commit_is_reported_not_rethrown(): void
    {
        Exceptions::fake();

        [$student, $requestType, $department] = $this->makeStudentRequestTypeAndDepartment('guarded');

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'request_type_id' => $requestType->id,
            'status' => 'pending',
            'is_reopened' => false,
        ]);

        // Break the database queue so dispatch throws a QueryException.
        config(['queue.connections.database.table' => 'nonexistent_queue_jobs_table']);

        // The recorder registers an afterCommit callback that dispatches the job.
        // Without the guard, the QueryException would propagate out of
        // DB::transaction(), falsely surfacing a committed mutation as a failure.
        DB::transaction(function () use ($request, $department) {
            app(RequestEventRecorder::class)->recordRequestCreated($request, $department->id);
        });

        // The event was persisted despite the dispatch failure.
        $this->assertDatabaseHas('request_events', [
            'event_name' => 'request.created',
            'delivery_status' => 'pending',
        ]);

        // The failure was reported, not swallowed silently.
        Exceptions::assertReported(\Illuminate\Database\QueryException::class);
    }

    /**
     * @return array{0: User, 1: RequestType, 2: Department}
     */
    private function makeStudentRequestTypeAndDepartment(string $suffix): array
    {
        $faculty = Faculty::create([
            'name' => "Faculty {$suffix}",
            'code' => "FAC-{$suffix}",
            'matricule_prefix' => "F{$suffix}",
        ]);

        $department = Department::create([
            'faculty_id' => $faculty->id,
            'name' => "Department {$suffix}",
            'code' => "DEP-{$suffix}",
            'type' => 'academic',
        ]);

        $programme = Programme::create([
            'department_id' => $department->id,
            'name' => "Programme {$suffix}",
            'code' => "PROG-{$suffix}",
            'degree_type' => 'BACHELOR',
        ]);

        $student = User::create([
            'name' => "Student {$suffix}",
            'email' => "{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'student',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'matricule' => "MAT-{$suffix}",
            'level' => '100',
            'status' => 'active',
        ]);

        $requestType = RequestType::create([
            'name' => "Request type {$suffix}",
            'description' => 'Queue slice request type.',
            'default_department_sequence' => [$department->id],
        ]);

        return [$student, $requestType, $department];
    }

    private function makeStaffInDepartment(Department $department, string $suffix): User
    {
        $staff = User::create([
            'name' => "Staff {$suffix}",
            'email' => "staff-{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'staff',
        ]);

        $profile = $staff->staffProfile()->create([
            'staff_id' => "STAFF-{$suffix}",
            'admin_level' => null,
        ]);

        $profile->departments()->attach($department->id, ['is_primary' => true]);

        return $staff;
    }
}

class QueueSliceStoreFailureUploadedFile extends UploadedFile
{
    private UploadedFile $sourceFile;

    public function __construct()
    {
        $this->sourceFile = UploadedFile::fake()->create('evidence.pdf', 24, 'application/pdf');

        parent::__construct(
            $this->sourceFile->getPathname(),
            $this->sourceFile->getClientOriginalName(),
            $this->sourceFile->getClientMimeType(),
            $this->sourceFile->getError(),
            true,
        );
    }

    public function store($path = '', $options = [])
    {
        return false;
    }

    public function getMimeType(): string
    {
        return 'application/pdf';
    }
}

class QueueSliceRecordingRequestEventTransport implements RequestEventTransport
{
    /** @var list<string> */
    public array $eventIds = [];

    public function publish(array $channels, array $event): void
    {
        $this->eventIds[] = $event['event_id'];
    }
}
