<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/services
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_list_services(): void
    {
        $user = User::factory()->create();

        Service::create(['name' => 'Logistique']);
        Service::create(['name' => 'Ressources Humaines']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Logistique')
            ->assertJsonPath('data.1.name', 'Ressources Humaines');
    }

    public function test_unauthenticated_user_cannot_list_services(): void
    {
        $response = $this->getJson('/api/services');

        $response->assertStatus(401);
    }

    public function test_returns_empty_list_when_no_services_exist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------------------------
    // POST /api/services
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_create_a_service(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/services', [
                'name' => 'Informatique',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Informatique');

        $this->assertDatabaseHas('services', ['name' => 'Informatique']);
    }

    public function test_cannot_create_service_with_duplicate_name(): void
    {
        $user = User::factory()->create();

        Service::create(['name' => 'Informatique']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/services', [
                'name' => 'Informatique',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_cannot_create_service_with_missing_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/services', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_unauthenticated_user_cannot_create_service(): void
    {
        $response = $this->postJson('/api/services', [
            'name' => 'Informatique',
        ]);

        $response->assertStatus(401);
    }

}
