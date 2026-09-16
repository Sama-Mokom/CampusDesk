<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Notification;
use App\Models\Programme;
use App\Models\Request;
use App\Models\RequestType;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_complete_seed_graph_is_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Faculty::count());
        $this->assertGreaterThan(0, Department::count());
        $this->assertSame(80, User::where('role', 'staff')->count());
        $this->assertSame(0, StaffProfile::where('admin_level', 'super_admin')->count());
        $this->assertGreaterThan(0, StudentProfile::count());
        $this->assertSame(24, Request::count());
        $this->assertSame(4, RequestType::count());
        foreach (['pending', 'in_review', 'forwarded', 'ready', 'rejected', 'collected'] as $status) {
            $this->assertSame(4, Request::where('status', $status)->count());
        }

        $this->assertSame(0, Department::whereNotIn('type', ['academic', 'records', 'admin'])->count());
        $this->assertFalse(Programme::with('department')->get()->contains(
            fn (Programme $programme) => $programme->faculty_id !== $programme->department->faculty_id,
        ));

        foreach (Faculty::where('code', '!=', 'RO')->get() as $faculty) {
            $this->assertSame(1, Department::where('faculty_id', $faculty->id)->where('code', 'REC-'.$faculty->code)->where('type', 'records')->count());
        }

        $this->assertNotNull(Faculty::where('code', 'RO')->first());
        foreach (['TRD', 'AOE', 'AOC'] as $code) {
            $this->assertSame('admin', Department::where('code', $code)->value('type'));
        }

        foreach (Department::all() as $department) {
            $this->assertSame(1, DB::table('department_staff')->where('department_id', $department->id)->where('is_primary', true)->count());
        }

        $this->assertSame(0, StudentProfile::query()->whereNotIn('level', ['100', '200', '300', '400'])->count());
        $this->assertSame(0, StudentProfile::query()->whereHas('department', fn ($query) => $query->where('type', '!=', 'academic'))->count());
        $this->assertSame(0, Request::doesntHave('requestStages')->count());
        $this->assertSame(0, Request::doesntHave('statusHistories')->count());
        $this->assertSame(3, DB::table('attachments')->count());
        $this->assertSame(4, Notification::where('type', 'seeded_demo')->count());
    }
}
