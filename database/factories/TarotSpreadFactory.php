<?php

namespace Database\Factories;

use App\Models\TarotSpread;
use Illuminate\Database\Eloquent\Factories\Factory;

class TarotSpreadFactory extends Factory
{
    protected $model = TarotSpread::class;

    public function definition(): array
    {
        return [
            'name'        => 'Three Card Spread',
            'name_zh'     => '三牌展開',
            'description' => '過去、現在、未來三個面向',
            'card_count'  => 3,
            'positions'   => [
                ['index' => 0, 'name' => '過去', 'description' => '影響現在的過去因素'],
                ['index' => 1, 'name' => '現在', 'description' => '當前的處境'],
                ['index' => 2, 'name' => '未來', 'description' => '可能的發展方向'],
            ],
        ];
    }
}
