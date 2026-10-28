<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test-db-error', function () {
            throw new QueryException(
                'mysql',
                'select * from secret_table where id = 1',
                [],
                new \Exception('Table secret_table does not exist')
            );
        });

        Route::get('/api/test-generic-error', function () {
            throw new \RuntimeException('Internal disk read failure at /var/secret/path');
        });

        Route::get('/api/test-domain-error', function () {
            abort(409, 'A custom business domain message.');
        });
    }

    public function test_database_exception_returns_clean_message_without_sql_leak(): void
    {
        $response = $this->getJson('/api/test-db-error');

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'A database error occurred while processing your request. Please try again later.',
            ]);

        $this->assertStringNotContainsString('secret_table', $response->getContent());
        $this->assertStringNotContainsString('select * from', $response->getContent());
    }

    public function test_generic_exception_returns_safe_500_message(): void
    {
        $response = $this->getJson('/api/test-generic-error');

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'An unexpected server error occurred. Please try again later.',
            ]);

        $this->assertStringNotContainsString('/var/secret/path', $response->getContent());
    }

    public function test_domain_http_exception_preserves_its_custom_message(): void
    {
        $response = $this->getJson('/api/test-domain-error');

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'A custom business domain message.',
            ]);
    }
}
