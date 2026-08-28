<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_landing_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
