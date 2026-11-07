<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home address sends visitors on (to log in, or to their dashboard). It used to
     * expect a page here; updated 2026-11-07 so the whole suite passes.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk();
    }
}
