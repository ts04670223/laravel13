<?php

namespace Database\Factories;

use App\Models\TarotCard;
use App\Models\TarotDeck;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarotCardFactory extends Factory
{
    protected $model = TarotCard::class;

    public function definition(): array
    {
        return [
            'deck_id'          => TarotDeck::factory(),
            'name'             => $this->faker->words(2, true),
            'name_zh'          => $this->faker->words(2, true),
            'arcana'           => $this->faker->randomElement(['major', 'minor']),
            'suit'             => $this->faker->randomElement(['wands', 'cups', 'swords', 'pentacles', null]),
            'number'           => $this->faker->numberBetween(0, 21),
            'image_path'       => 'major-00-fool.jpg',
            'upright_meaning'  => $this->faker->sentence(),
            'reversed_meaning' => $this->faker->sentence(),
            'keywords_upright'  => ['新開始', '冒險'],
            'keywords_reversed' => ['魯莽', '衝動'],
        ];
    }
}
