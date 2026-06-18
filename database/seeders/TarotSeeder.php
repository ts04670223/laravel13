<?php

namespace Database\Seeders;

use App\Models\TarotCard;
use App\Models\TarotDeck;
use App\Models\TarotSpread;
use Illuminate\Database\Seeder;

class TarotSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 建立偉特塔羅牌組
        $deck = TarotDeck::firstOrCreate(
            ['name' => 'Rider-Waite Tarot'],
            [
                'description' => '1909 年由 A.E. Waite 設計，Pamela Coleman Smith 繪圖的經典塔羅牌組。',
                'is_active'   => true,
            ]
        );

        // 2. 寫入 78 張牌
        $cards = require __DIR__ . '/data/tarot_cards.php';
        foreach ($cards as $cardData) {
            TarotCard::firstOrCreate(
                ['deck_id' => $deck->id, 'name' => $cardData['name']],
                array_merge($cardData, ['deck_id' => $deck->id])
            );
        }

        // 3. 寫入牌陣
        $spreads = require __DIR__ . '/data/tarot_spreads.php';
        foreach ($spreads as $spreadData) {
            TarotSpread::firstOrCreate(
                ['name' => $spreadData['name']],
                $spreadData
            );
        }

        $this->command->info('TarotSeeder: ' . count($cards) . ' 張牌、' . count($spreads) . ' 種牌陣已寫入。');
    }
}
