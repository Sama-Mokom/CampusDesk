<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeptAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'dept_admin'])
            ->get('/api/test/dept-admin', fn () => response()->noContent());
    }

    public function test_only_department_admins_pass_the_department_admin_gate(): void
    {
        $departmentAdmin = $this->makeStaff('dept_admin');
        $plainStaff = User::factory()->staff()->create();
        $superAdmin = $this->makeStaff('super_admin');

        $this->assertTrue(Gate::forUser($departmentAdmin)->allows('is-dept-admin'));
        $this->assertFalse(Gate::forUser($plainStaff)->allows('is-dept-admin'));
        $this->assertFalse(Gate::forUser($superAdmin)->allows('is-dept-admin'));
    }

    public function test_department_admin_middleware_allows_only_department_admins(): void
    {
        $departmentAdmin = $this->makeStaff('dept_admin');
        $plainStaff = User::factory()->staff()->create();

        $this->actingAs($departmentAdmin, 'sanctum')
            ->getJson('/api/test/dept-admin')
            ->assertNoContent();

        $this->actingAs($plainStaff, 'sanctum')
            ->getJson('/api/test/dept-admin')
            ->assertForbidden();
    }

    private function makeStaff(?string $adminLevel = null): User
    {
        $user = User::factory()->staff()->create();
        $user->staffProfile->update(['admin_level' => $adminLevel]);

        return $user->fresh('staffProfile');
    }
}
