<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SchoolAdmin>
 */
class SchoolAdminFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'school_id' => \App\Models\School::factory(),
            'phone' => $this->faker->phoneNumber,
            'profile_photo' => $this->faker->optional()->imageUrl(200, 200),
            'permissions' => json_encode(['manage_students', 'manage_classes', 'view_reports']),
        ];
    }
}
