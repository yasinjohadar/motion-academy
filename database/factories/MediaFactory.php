<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'uuid' => (string) Str::uuid(),
            'disk' => 'media',
            'path' => "media/{$name}.jpg",
            // The columns are `filename`, `mime` and `extension` — not
            // `original_name` / `mime_type`.
            'filename' => "{$name}.jpg",
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => fake()->numberBetween(20_000, 2_000_000),
            'width' => 1200,
            'height' => 800,
            'uploaded_by' => User::factory(),
        ];
    }

    public function document(): static
    {
        $name = fake()->unique()->slug(2);

        return $this->state(fn (): array => [
            'path' => "media/{$name}.pdf",
            'filename' => "{$name}.pdf",
            'mime' => 'application/pdf',
            'extension' => 'pdf',
            'width' => null,
            'height' => null,
        ]);
    }
}
