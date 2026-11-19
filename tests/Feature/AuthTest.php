<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_gets_a_token()
    {
        $this->postJson('/api/register', [
            'name' => 'Ruba', 'email' => 'ruba@example.com', 'password' => 'secret-pass',
        ])
            ->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $user = User::where('email', 'ruba@example.com')->first();
        $this->assertNotSame('secret-pass', $user->password);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_registration_is_validated()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', ['name' => '', 'email' => 'taken@example.com', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_a_user_can_log_in()
    {
        User::factory()->create(['email' => 'ruba@example.com', 'password' => Hash::make('secret-pass')]);

        $this->postJson('/api/login', ['email' => 'ruba@example.com', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_a_wrong_password_is_rejected()
    {
        User::factory()->create(['email' => 'ruba@example.com', 'password' => Hash::make('secret-pass')]);

        $this->postJson('/api/login', ['email' => 'ruba@example.com', 'password' => 'wrong'])
            ->assertStatus(401);
    }

    public function test_the_user_endpoint_needs_a_token()
    {
        $this->getJson('/api/user')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create(['name' => 'Ruba']));

        $this->getJson('/api/user')->assertOk()->assertJsonPath('name', 'Ruba');
    }

    public function test_logging_out_removes_the_token()
    {
        User::factory()->create(['email' => 'ruba@example.com', 'password' => Hash::make('secret-pass')]);

        $token = $this->postJson('/api/login', ['email' => 'ruba@example.com', 'password' => 'secret-pass'])
            ->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
