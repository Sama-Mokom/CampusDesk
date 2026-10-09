<?php

namespace Tests\Feature;

use App\Models\AdministrativeAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoCredentialRemediationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance('env', 'staging');
    }

    public function test_dry_run_selects_only_known_password_users_and_changes_nothing(): void
    {
        $vulnerable = User::factory()->staff()->create();
        $retained = User::factory()->staff()->create(['password' => 'unique-retained-password']);

        $this->artisan('staging:remediate-demo-credentials --dry-run')
            ->expectsOutputToContain('Staff: 1')
            ->expectsOutputToContain('Total: 1')
            ->expectsOutputToContain('Dry run only')
            ->assertSuccessful();

        $this->assertNull($vulnerable->fresh()->disabled_at);
        $this->assertNull($retained->fresh()->disabled_at);
        $this->assertTrue(Hash::check('password', $vulnerable->fresh()->password));
        $this->assertTrue(Hash::check('unique-retained-password', $retained->fresh()->password));
        $this->assertDatabaseCount('administrative_actions', 0);
    }

    public function test_command_refuses_to_run_outside_staging(): void
    {
        $this->app->instance('env', 'production');
        User::factory()->staff()->create();

        $this->artisan('staging:remediate-demo-credentials --dry-run')
            ->expectsOutputToContain('only run when APP_ENV=staging')
            ->assertFailed();

        $this->assertDatabaseCount('administrative_actions', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_super_admin_match_aborts_before_confirmation_or_changes(): void
    {
        $admin = User::factory()->staff()->create();
        $admin->staffProfile->update(['admin_level' => 'super_admin']);
        $ordinary = User::factory()->staff()->create();

        $this->artisan('staging:remediate-demo-credentials --apply')
            ->expectsOutputToContain('at least one affected account is a Super Admin')
            ->assertFailed();

        $this->assertNull($admin->fresh()->disabled_at);
        $this->assertNull($ordinary->fresh()->disabled_at);
    }

    public function test_retained_persona_password_can_be_rotated_without_disabling_the_account(): void
    {
        $persona = User::factory()->staff()->create();
        $token = $persona->createToken('before-persona-rotation');
        DB::table('password_reset_tokens')->insert([
            'email' => $persona->email,
            'token' => Hash::make('pending-reset'),
            'created_at' => now(),
        ]);

        $this->artisan("staging:rotate-persona-password {$persona->id}")
            ->expectsQuestion('New password (minimum 16 characters)', 'unique-persona-password-2026')
            ->expectsQuestion('Confirm new password', 'unique-persona-password-2026')
            ->expectsOutputToContain('existing credentials revoked')
            ->assertSuccessful();

        $persona->refresh();
        $this->assertNull($persona->disabled_at);
        $this->assertTrue(Hash::check('unique-persona-password-2026', $persona->password));
        $this->assertFalse(Hash::check('password', $persona->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $persona->email]);
        $this->assertDatabaseHas('administrative_actions', [
            'action' => 'user.password_rotated',
            'subject_id' => $persona->id,
        ]);

        $this->artisan('staging:remediate-demo-credentials --dry-run')
            ->expectsOutputToContain('Total: 0')
            ->assertSuccessful();
    }

    public function test_apply_invalidates_credentials_audits_and_is_idempotent(): void
    {
        $vulnerable = User::factory()->staff()->create();
        $retained = User::factory()->staff()->create(['password' => 'unique-retained-password']);
        $token = $vulnerable->createToken('active');
        $oldRememberToken = $vulnerable->remember_token;
        DB::table('password_reset_tokens')->insert([
            'email' => $vulnerable->email,
            'token' => Hash::make('pending-reset'),
            'created_at' => now(),
        ]);

        $this->artisan('staging:remediate-demo-credentials --apply')
            ->expectsConfirmation('Disable and rotate credentials for all affected accounts?', 'yes')
            ->expectsOutputToContain('Accounts changed: 1')
            ->assertSuccessful();

        $vulnerable->refresh();
        $this->assertNotNull($vulnerable->disabled_at);
        $this->assertFalse(Hash::check('password', $vulnerable->password));
        $this->assertNotSame($oldRememberToken, $vulnerable->remember_token);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $vulnerable->email]);
        $this->assertDatabaseHas('administrative_actions', [
            'actor_id' => null,
            'action' => 'demo_credentials.remediated',
            'subject_id' => $vulnerable->id,
        ]);
        $this->assertNull($retained->fresh()->disabled_at);
        $this->assertTrue(Hash::check('unique-retained-password', $retained->fresh()->password));

        $this->artisan('staging:remediate-demo-credentials --dry-run')
            ->expectsOutputToContain('Total: 0')
            ->assertSuccessful();
        $this->assertSame(1, AdministrativeAction::count());
    }
}
