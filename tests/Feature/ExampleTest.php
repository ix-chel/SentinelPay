<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The application health check must return 200.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'ok']);
    }
}
