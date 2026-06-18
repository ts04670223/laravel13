<?php

namespace Database\Factories;

use App\Models\TarotDeck;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarotDeckFactory extends Factory
{
    protected $model = TarotDeck::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'is_active'   => true,
        ];
    }
}
