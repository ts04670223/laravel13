<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\TarotReading;
use App\Models\TarotSpread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarotReadingFactory extends Factory
{
    protected $model = TarotReading::class;

    public function definition(): array
    {
        $user         = User::factory()->create();
        $spread       = TarotSpread::factory()->create(['card_count' => 3]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'type'    => 'tarot',
        ]);

        return [
            'user_id'           => $user->id,
            'conversation_id'   => $conversation->id,
            'spread_id'         => $spread->id,
            'question'          => fake()->sentence(),
            'reading_style'     => fake()->randomElement(['mystic', 'rational']),
            'drawn_cards'       => [
                ['position' => 0, 'card_id' => 1, 'is_reversed' => false],
            ],
            'ai_interpretation' => null,
        ];
    }
}
