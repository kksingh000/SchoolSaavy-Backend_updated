<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
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
            'employee_id' => fake()->unique()->numerify('EMP####'),
            'phone' => fake()->phoneNumber,
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-22 years'),
            'joining_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'qualification' => fake()->randomElement(['B.Ed', 'M.Ed', 'B.A', 'M.A', 'B.Sc', 'M.Sc', 'PhD']),
            'profile_photo' => fake()->optional()->imageUrl(200, 200, 'people'),
            'address' => fake()->address,
            'specializations' => json_encode(fake()->words(3)),
        ];
    }
}
