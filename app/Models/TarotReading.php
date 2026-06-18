<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarotReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'conversation_id', 'spread_id',
        'question', 'reading_style', 'drawn_cards', 'ai_interpretation',
    ];

    protected $casts = ['drawn_cards' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function spread(): BelongsTo
    {
        return $this->belongsTo(TarotSpread::class, 'spread_id');
    }
}
