<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        // The first 5 attempts are processed (wrong password gives a 422)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email'    => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // The 6th attempt is blocked, even with the right password
        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertStatus(429);
    }

    public function test_root_url_is_not_exposed(): void
    {
        $this->get('/')->assertStatus(404);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        // Token lifetime is 480 minutes
        $this->travel(481)->minutes();

        $this->withToken($token)->getJson('/api/user')->assertStatus(401);
    }
}
