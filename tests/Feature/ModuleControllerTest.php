<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Module;
use Laravel\Sanctum\Sanctum;

class ModuleControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $school;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test school
        $this->school = School::factory()->create([
            'name' => 'Test School',
            'email' => 'test@school.com',
            'is_active' => true
        ]);

        // Create authenticated user
        $this->user = User::factory()->create(['user_type' => 'school_admin']);
        SchoolAdmin::factory()->create([
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
    }

    public function test_can_get_module_pricing_without_authentication()
    {
        // Create some modules
        Module::factory()->count(5)->create();

        $response = $this->getJson('/api/modules/pricing');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'price',
                        'features'
                    ]
                ]
            ]);
    }

    public function test_can_list_all_modules_without_authentication()
    {
        Module::factory()->count(3)->create();

        $response = $this->getJson('/api/modules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'price',
                            'is_active'
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_show_module_details_without_authentication()
    {
        $module = Module::factory()->create();

        $response = $this->getJson("/api/modules/{$module->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'features'
                ]
            ]);
    }

    public function test_can_get_school_modules_when_authenticated()
    {
        Sanctum::actingAs($this->user);

        // Create modules and activate some for the school
        $module1 = Module::factory()->create(['name' => 'Student Management']);
        $module2 = Module::factory()->create(['name' => 'Fee Management']);
        $module3 = Module::factory()->create(['name' => 'Attendance']);

        // Activate modules for school
        $this->school->modules()->attach([
            $module1->id => ['activated_at' => now(), 'is_active' => true],
            $module2->id => ['activated_at' => now(), 'is_active' => true],
        ]);

        $response = $this->getJson('/api/modules/school');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'active_modules' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'price',
                            'pivot' => [
                                'is_active',
                                'activated_at'
                            ]
                        ]
                    ],
                    'available_modules' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'price'
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_activate_module()
    {
        Sanctum::actingAs($this->user);

        $module = Module::factory()->create();

        $response = $this->postJson("/api/modules/{$module->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Module activated successfully'
            ]);

        // Check if module is activated in database
        $this->assertDatabaseHas('school_modules', [
            'school_id' => $this->school->id,
            'module_id' => $module->id,
            'is_active' => true
        ]);
    }

    public function test_can_deactivate_module()
    {
        Sanctum::actingAs($this->user);

        $module = Module::factory()->create();

        // First activate the module
        $this->school->modules()->attach($module->id, [
            'activated_at' => now(),
            'is_active' => true
        ]);

        $response = $this->postJson("/api/modules/{$module->id}/deactivate");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Module deactivated successfully'
            ]);

        // Check if module is deactivated in database
        $this->assertDatabaseHas('school_modules', [
            'school_id' => $this->school->id,
            'module_id' => $module->id,
            'is_active' => false
        ]);
    }

    public function test_cannot_activate_already_active_module()
    {
        Sanctum::actingAs($this->user);

        $module = Module::factory()->create();

        // Activate the module first
        $this->school->modules()->attach($module->id, [
            'activated_at' => now(),
            'is_active' => true
        ]);

        $response = $this->postJson("/api/modules/{$module->id}/activate");

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Module is already active'
            ]);
    }

    public function test_cannot_deactivate_inactive_module()
    {
        Sanctum::actingAs($this->user);

        $module = Module::factory()->create();

        $response = $this->postJson("/api/modules/{$module->id}/deactivate");

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Module is not active'
            ]);
    }

    public function test_module_activation_requires_authentication()
    {
        $module = Module::factory()->create();

        $response = $this->postJson("/api/modules/{$module->id}/activate");
        $response->assertStatus(401);

        $response = $this->postJson("/api/modules/{$module->id}/deactivate");
        $response->assertStatus(401);
    }

    public function test_school_modules_require_authentication()
    {
        $response = $this->getJson('/api/modules/school');
        $response->assertStatus(401);
    }

    public function test_cannot_activate_nonexistent_module()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/modules/999/activate');
        $response->assertStatus(404);
    }
}
