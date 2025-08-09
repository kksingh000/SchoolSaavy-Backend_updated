<?php

namespace Database\Factories;

use App\Models\GalleryAlbum;
use App\Models\School;
use App\Models\ClassRoom;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryAlbumFactory extends Factory
{
    protected $model = GalleryAlbum::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'class_id' => ClassRoom::factory(),
            'event_id' => Event::factory(),
            'created_by' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'event_date' => $this->faker->dateTimeBetween('-1 year', '+1 month'),
            'status' => $this->faker->randomElement(['active', 'inactive', 'archived']),
            'media_count' => $this->faker->numberBetween(0, 50),
            'cover_image' => null,
            'is_public' => $this->faker->boolean(80), // 80% chance of being public
            'visibility_settings' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function withMedia(): static
    {
        return $this->state(fn(array $attributes) => [
            'media_count' => $this->faker->numberBetween(5, 25),
            'cover_image' => 'gallery/sample/cover_' . $this->faker->uuid() . '.jpg',
        ]);
    }

    public function publicGallery(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_public' => true,
        ]);
    }

    public function privateGallery(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_public' => false,
        ]);
    }
}
