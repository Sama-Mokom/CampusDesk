<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DestructiveCommandsProhibitedTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);
        parent::tearDown();
    }

    public function test_destructive_database_commands_are_prohibited_in_staging(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        (new AppServiceProvider($this->app))->boot();

        foreach ([
            'migrate:fresh --force',
            'migrate:refresh --force',
            'migrate:reset --force',
            'migrate:rollback --force',
            'db:wipe --force',
        ] as $command) {
            $this->artisan($command)->assertExitCode(1);
        }
    }

    public function test_destructive_database_commands_are_prohibited_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        (new AppServiceProvider($this->app))->boot();

        $this->artisan('migrate:fresh --force')->assertExitCode(1);
        $this->artisan('db:wipe --force')->assertExitCode(1);
    }

    public function test_destructive_database_commands_are_not_prohibited_in_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'testing');
        (new AppServiceProvider($this->app))->boot();

        $reflection = new \ReflectionProperty(FreshCommand::class, 'prohibitedFromRunning');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->getValue());

        foreach ([
            RefreshCommand::class,
            ResetCommand::class,
            RollbackCommand::class,
            WipeCommand::class,
        ] as $commandClass) {
            $prop = new \ReflectionProperty($commandClass, 'prohibitedFromRunning');
            $prop->setAccessible(true);
            $this->assertFalse($prop->getValue());
        }
    }
}
