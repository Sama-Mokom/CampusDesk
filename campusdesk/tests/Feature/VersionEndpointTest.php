<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionEndpointTest extends TestCase
{
    public function test_version_endpoint_returns_the_baked_build_identity(): void
    {
        config([
            'app.build_sha' => '0123456789abcdef0123456789abcdef01234567',
            'app.build_time' => '2026-11-25T12:00:00+00:00',
        ]);

        $response = $this->getJson('/api/version');

        $response
            ->assertOk()
            ->assertExactJson([
                'build_sha' => '0123456789abcdef0123456789abcdef01234567',
                'build_time' => '2026-11-25T12:00:00+00:00',
            ]);

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }
}
