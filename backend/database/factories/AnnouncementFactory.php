<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'image_path' => null,
            'display_days' => fake()->numberBetween(1, 30),
        ];
    }

    /**
     * Indicate that the announcement's display window has already ended.
     *
     * Sets created_at/expires_at directly into the past via forceFill() +
     * saveQuietly(), bypassing the model's "saving" event so the
     * booted() hook does not recompute expires_at from display_days and
     * overwrite these deliberately expired testing values.
     */
    public function expired(): static
    {
        return $this->afterCreating(function (Announcement $announcement) {
            $announcement->forceFill([
                'created_at' => now()->subDays(10),
                'expires_at' => now()->subDays(9),
            ])->saveQuietly();
        });
    }
}
