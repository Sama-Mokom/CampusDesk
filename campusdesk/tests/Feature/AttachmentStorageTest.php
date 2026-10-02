<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\RequestType;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_student_can_upload_an_attachment_and_only_authorized_users_can_download_it(): void
    {
        [$student, $requestType] = $this->makeStudentAndRequestType('owner');

        $attachment = $this->uploadAttachment($student, $requestType);

        $this->assertNotEmpty($attachment->file_path);
        $this->assertNotSame('0', $attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->actingAs($student, 'sanctum')
            ->get("/api/attachments/{$attachment->id}")
            ->assertOk();

        $stage = $attachment->request->requestStages()->firstOrFail();
        $departmentStaff = $this->makeStaff('department', $stage->department);

        $this->actingAs($departmentStaff, 'sanctum')
            ->get("/api/attachments/{$attachment->id}")
            ->assertOk();

        $assignedStaff = $this->makeStaff('assigned');
        $stage->update(['handled_by' => $assignedStaff->id]);

        $this->actingAs($assignedStaff, 'sanctum')
            ->get("/api/attachments/{$attachment->id}")
            ->assertOk();

        $superAdmin = $this->makeStaff('super-admin', null, 'super_admin');

        $this->actingAs($superAdmin, 'sanctum')
            ->get("/api/attachments/{$attachment->id}")
            ->assertOk();

        [$unrelatedStudent, , $unrelatedDepartment] = $this->makeStudentAndRequestType('other');
        $unrelatedStaff = $this->makeStaff('unrelated', $unrelatedDepartment);

        $this->actingAs($unrelatedStudent, 'sanctum')
            ->get("/api/attachments/{$attachment->id}")
            ->assertForbidden();

        $this->actingAs($unrelatedStaff, 'sanctum')
            ->get("/api/attachments/{$attachment->id}")
            ->assertForbidden();
    }

    public function test_missing_attachment_file_returns_not_found(): void
    {
        [$student, $requestType] = $this->makeStudentAndRequestType('missing-file');
        $attachment = $this->uploadAttachment($student, $requestType);

        Storage::disk('local')->delete($attachment->file_path);

        $this->actingAs($student, 'sanctum')
            ->get("/api/attachments/{$attachment->id}")
            ->assertNotFound();
    }

    public function test_failed_attachment_write_rolls_back_the_request_and_attachment_record(): void
    {
        [$student, $requestType] = $this->makeStudentAndRequestType('write-failure');

        $this->actingAs($student, 'sanctum')
            ->post('/api/requests', [
                'request_type_id' => $requestType->id,
                'attachments' => [new StoreResultUploadedFile(false)],
            ])
            ->assertServerError();

        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_partial_attachment_write_failure_cleans_up_previously_stored_files(): void
    {
        [$student, $requestType] = $this->makeStudentAndRequestType('partial-failure');
        $storedPath = 'attachments/first-upload.pdf';

        $this->actingAs($student, 'sanctum')
            ->post('/api/requests', [
                'request_type_id' => $requestType->id,
                'attachments' => [
                    new StoreResultUploadedFile($storedPath),
                    new StoreResultUploadedFile(false),
                ],
            ])
            ->assertServerError();

        Storage::disk('local')->assertMissing($storedPath);
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    /**
     * @return array{0: User, 1: RequestType, 2: Department}
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
            'description' => 'Attachment storage test request type.',
            'default_department_sequence' => [$department->id],
        ]);

        return [$student, $requestType, $department];
    }

    private function makeStaff(string $suffix, ?Department $department = null, ?string $adminLevel = null): User
    {
        $staff = User::create([
            'name' => "Staff {$suffix}",
            'email' => "{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'staff',
        ]);

        $profile = $staff->staffProfile()->create([
            'staff_id' => "STAFF-{$suffix}",
            'admin_level' => $adminLevel,
        ]);

        if ($department !== null) {
            $profile->departments()->attach($department->id, ['is_primary' => true]);
        }

        return $staff;
    }

    private function uploadAttachment(User $student, RequestType $requestType): Attachment
    {
        $file = UploadedFile::fake()->create('evidence.pdf', 24, 'application/pdf');

        $this->actingAs($student, 'sanctum')
            ->post('/api/requests', [
                'request_type_id' => $requestType->id,
                'description' => 'Please process this request.',
                'attachments' => [$file],
            ])
            ->assertCreated();

        return Attachment::sole();
    }
}

class StoreResultUploadedFile extends UploadedFile
{
    public function __construct(private string|false $storeResult)
    {
        $file = UploadedFile::fake()->create('evidence.pdf', 24, 'application/pdf');

        parent::__construct(
            $file->getPathname(),
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            $file->getError(),
            true,
        );
    }

    public function store($path = '', $options = [])
    {
        if (is_string($this->storeResult)) {
            Storage::disk('local')->put($this->storeResult, 'test attachment');
        }

        return $this->storeResult;
    }
}
