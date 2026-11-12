<?php

namespace Tests\Feature;

use App\Models\Bookable;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoint_exposes_only_whitelisted_fields(): void
    {
        $this->actingAs(User::factory()->create(['address' => 'Via Roma 1']));

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'address', 'role'])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token')
            ->assertJsonMissingPath('email_verified_at');
    }

    public function test_bookings_list_keeps_laravel_paginator_shape_with_resource_items(): void
    {
        $user = User::factory()->create();
        Booking::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        // Il frontend legge current_page, total, ...: la forma del paginatore non deve cambiare
        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonStructure([
                'current_page', 'per_page', 'total', 'last_page',
                'data' => [['id', 'status', 'starts_at', 'bookable' => ['id', 'name'], 'user' => ['id', 'name']]],
            ])
            ->assertJsonMissingPath('data.0.user.password');
    }

    public function test_bookable_without_loaded_relation_omits_availabilities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // Dopo la creazione la relazione non è caricata: il campo non deve comparire
        $this->postJson('/api/bookables', ['name' => 'Sala', 'slot_minutes' => 30])
            ->assertCreated()
            ->assertJsonPath('name', 'Sala')
            ->assertJsonMissingPath('availabilities');
    }
}
