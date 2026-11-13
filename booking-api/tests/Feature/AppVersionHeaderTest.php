<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ogni risposta dell'API dichiara la versione pubblicata, così il frontend si accorge di essere vecchio. */
class AppVersionHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_response_carries_the_published_version(): void
    {
        config(['app.version' => 'a3f9c1b']);
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/user')->assertOk()->assertHeader('X-App-Version', 'a3f9c1b');
    }

    public function test_an_unauthenticated_response_carries_the_version_too(): void
    {
        config(['app.version' => 'a3f9c1b']);

        $this->getJson('/api/user')->assertUnauthorized()->assertHeader('X-App-Version', 'a3f9c1b');
    }

    public function test_a_validation_error_carries_the_version_too(): void
    {
        config(['app.version' => 'a3f9c1b']);
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [])->assertUnprocessable()->assertHeader('X-App-Version', 'a3f9c1b');
    }

    public function test_a_route_outside_the_api_does_not_carry_the_header(): void
    {
        $this->get('/up')->assertHeaderMissing('X-App-Version');
    }

    public function test_the_version_is_dev_when_nothing_is_configured(): void
    {
        $this->assertSame('dev', config('app.version'));
    }
}
