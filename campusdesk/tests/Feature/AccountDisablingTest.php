<?php

namespace Tests\Feature;

use App\Models\AdministrativeAction;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class AccountDisablingTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->staff()->create();
        $user->staffProfile->update(['admin_level' => 'super_admin']);

        return $user->fresh('staffProfile');
    }

    public function test_enabled_account_can_log_in_and_disabled_account_gets_the_generic_failure(): void
    {
        $enabled = User::factory()->staff()->create();
        $disabled = User::factory()->staff()->create(['disabled_at' => now()]);

        $failure = $this->postJson('/api/login', ['email' => $disabled->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->json('errors.email.0');
        $invalidFailure = $this->postJson('/api/login', ['email' => $enabled->email, 'password' => 'incorrect'])
            ->assertUnprocessable()
            ->json('errors.email.0');

        $this->assertSame($invalidFailure, $failure);
        $this->postJson('/api/login', ['email' => $enabled->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_disabled_account_with_an_existing_token_is_rejected(): void
    {
        $user = User::factory()->staff()->create();
        $token = $user->createToken('existing')->plainTextToken;
        $user->forceFill(['disabled_at' => now()])->save();

        $this->withToken($token)->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.', 'code' => 'ACCOUNT_DISABLED']);
    }

    public function test_super_admin_can_disable_another_user_and_all_credentials_are_invalidated(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->staff()->create();
        $token = $target->createToken('active');
        $oldRememberToken = $target->remember_token;
        DB::table('password_reset_tokens')->insert([
            'email' => $target->email,
            'token' => Hash::make('reset-token'),
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$target->id}/disable", ['reason' => 'Unused demonstration account'])
            ->assertOk()
            ->assertJsonPath('data.is_disabled', true);

        $target->refresh();
        $this->assertNotNull($target->disabled_at);
        $this->assertNotSame($oldRememberToken, $target->remember_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseHas('administrative_actions', [
            'actor_id' => $admin->id,
            'action' => 'user.disabled',
            'subject_id' => $target->id,
        ]);
    }

    public function test_ordinary_users_cannot_disable_or_enable_accounts(): void
    {
        $ordinary = User::factory()->staff()->create();
        $target = User::factory()->staff()->create(['disabled_at' => now()]);

        $this->actingAs($ordinary, 'sanctum')
            ->patchJson("/api/admin/users/{$target->id}/disable")
            ->assertForbidden();
        $this->patchJson("/api/admin/users/{$target->id}/enable")
            ->assertForbidden();
    }

    public function test_super_admin_cannot_disable_themselves(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$admin->id}/disable")
            ->assertStatus(409);

        $this->assertNull($admin->fresh()->disabled_at);
    }

    public function test_disabled_secondary_super_admin_does_not_count_as_active_during_demotion(): void
    {
        $admin = $this->superAdmin();
        $secondary = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$secondary->id}/disable")
            ->assertOk();
        $this->patchJson("/api/admin/users/{$secondary->id}/admin-level", ['admin_level' => null])
            ->assertOk();

        $this->assertNull($secondary->staffProfile->fresh()->admin_level);
    }

    public function test_enable_is_idempotent_does_not_restore_tokens_and_user_can_use_rotated_password(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->staff()->create();
        $faculty = Faculty::create(['name' => 'Science', 'code' => 'SCI', 'matricule_prefix' => 'SC']);
        $department = Department::create(['faculty_id' => $faculty->id, 'name' => 'Math', 'code' => 'MTH', 'type' => 'academic']);
        $target->staffProfile->departments()->attach($department->id, ['is_primary' => true]);
        $oldToken = $target->createToken('before-admin-password-rotation');
        $oldRememberToken = $target->remember_token;
        DB::table('password_reset_tokens')->insert([
            'email' => $target->email,
            'token' => Hash::make('pending-reset'),
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$target->id}", ['password' => 'new-secure-password'])
            ->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldToken->accessToken->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertNotSame($oldRememberToken, $target->fresh()->remember_token);
        $this->patchJson("/api/admin/users/{$target->id}/disable")->assertOk();
        $this->patchJson("/api/admin/users/{$target->id}/disable")->assertOk();
        $this->patchJson("/api/admin/users/{$target->id}/enable")->assertOk();
        $this->patchJson("/api/admin/users/{$target->id}/enable")->assertOk();

        $this->assertSame(1, AdministrativeAction::where('action', 'user.disabled')->where('subject_id', $target->id)->count());
        $this->assertSame(1, AdministrativeAction::where('action', 'user.enabled')->where('subject_id', $target->id)->count());
        $this->assertSame(1, AdministrativeAction::where('action', 'user.password_rotated')->where('subject_id', $target->id)->count());
        $this->assertSame(0, $target->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/login', ['email' => $target->email, 'password' => 'new-secure-password'])
            ->assertOk();
    }

    public function test_audit_values_never_contain_secrets(): void
    {
        $admin = $this->superAdmin();
        $target = User::factory()->staff()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$target->id}/disable", ['reason' => 'No longer needed'])
            ->assertOk();

        $encoded = json_encode(AdministrativeAction::firstOrFail()->toArray());
        $this->assertStringNotContainsString($target->password, $encoded);
        $this->assertStringNotContainsString('password', strtolower($encoded));
        $this->assertStringNotContainsString('bearer', strtolower($encoded));
        $this->assertStringNotContainsString('reset-token', strtolower($encoded));

        $action = AdministrativeAction::firstOrFail();
        $this->expectException(LogicException::class);
        $action->update(['action' => 'tampered']);
    }
}
