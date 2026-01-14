<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrainingAttachment;
use App\Models\TrainingLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingAttachment>
 */
class TrainingAttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = TrainingAttachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileType = $this->faker->randomElement(['image', 'video', 'document']);
        
        $extensions = [
            'image' => ['jpg', 'png', 'jpeg'],
            'video' => ['mp4', 'mov', 'avi'],
            'document' => ['pdf', 'doc', 'docx'],
        ];

        $extension = $this->faker->randomElement($extensions[$fileType]);
        $fileName = $this->faker->word() . '.' . $extension;

        return [
            'training_log_id' => TrainingLog::factory(),
            'file_type' => $fileType,
            'file_path' => 'training_attachments/' . $fileName,
            'file_name' => $fileName,
            'uploaded_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the attachment is an image.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_type' => 'image',
            'file_path' => 'training_attachments/' . $this->faker->word() . '.jpg',
            'file_name' => $this->faker->word() . '.jpg',
        ]);
    }

    /**
     * Indicate that the attachment is a video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_type' => 'video',
            'file_path' => 'training_attachments/' . $this->faker->word() . '.mp4',
            'file_name' => $this->faker->word() . '.mp4',
        ]);
    }

    /**
     * Indicate that the attachment is a document.
     */
    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_type' => 'document',
            'file_path' => 'training_attachments/' . $this->faker->word() . '.pdf',
            'file_name' => $this->faker->word() . '.pdf',
        ]);
    }
}
