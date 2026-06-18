<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarotSpread extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_zh', 'description', 'card_count', 'positions',
    ];

    protected $casts = ['positions' => 'array'];

    public function readings(): HasMany
    {
        return $this->hasMany(TarotReading::class, 'spread_id');
    }
}
