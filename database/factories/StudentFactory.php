<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => \App\Models\School::factory(),
            'admission_number' => fake()->unique()->numerify('ADM####'),
            'roll_number' => fake()->unique()->numerify('ROLL####'),
            'first_name' => fake()->firstName,
            'last_name' => fake()->lastName,
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-5 years'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'admission_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'blood_group' => fake()->optional()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'profile_photo' => fake()->optional()->imageUrl(200, 200, 'people'),
            'address' => fake()->address,
            'phone' => fake()->optional()->phoneNumber,
            'is_active' => true,
        ];
    }
}
