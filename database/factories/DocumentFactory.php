<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'source_type' => fake()->randomElement(['file', 'url', 'database']),
            'source_path' => fake()->url(),
            'mime_type' => 'text/plain',
            'status' => 'completed',
            'metadata' => null,
        ];
    }
}
