<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolAdmin;
use Laravel\Sanctum\Sanctum;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $school;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test school
        $this->school = School::factory()->create([
            'name' => 'Test School',
            'email' => 'test@school.com',
            'is_active' => true
        ]);
    }

    public function test_login_with_valid_credentials()
    {
        // Create a user with school admin
        $user = User::factory()->create([
            'email' => 'principal@test.com',
            'password' => bcrypt('password123'),
            'user_type' => 'school_admin',
        ]);

        SchoolAdmin::factory()->create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'principal@test.com',
            'password' => 'password123',
            'user_type' => 'school_admin',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'user_type'
                ],
                'token'
            ]);
    }

    public function test_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@email.com',
            'password' => 'wrongpassword',
            'user_type' => 'school_admin',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Login failed',
                'error' => 'The provided credentials are incorrect.'
            ]);
    }

    public function test_login_validation_errors()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_endpoint_returns_authenticated_user()
    {
        $user = User::factory()->create(['user_type' => 'school_admin']);
        SchoolAdmin::factory()->create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'user_type'
                ]
            ]);
    }

    public function test_logout_endpoint()
    {
        $user = User::factory()->create(['user_type' => 'school_admin']);
        SchoolAdmin::factory()->create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out'
            ]);
    }

    public function test_unauthenticated_access_to_protected_routes()
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }
}
