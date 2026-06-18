<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarotCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'deck_id', 'name', 'name_zh', 'arcana', 'suit',
        'number', 'image_path', 'upright_meaning',
        'reversed_meaning', 'keywords_upright', 'keywords_reversed',
    ];

    protected $casts = [
        'keywords_upright'  => 'array',
        'keywords_reversed' => 'array',
    ];

    public function deck(): BelongsTo
    {
        return $this->belongsTo(TarotDeck::class, 'deck_id');
    }
}
