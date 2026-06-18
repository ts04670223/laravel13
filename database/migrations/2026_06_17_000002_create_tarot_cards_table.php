<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarot_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deck_id')->constrained('tarot_decks')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_zh');
            $table->enum('arcana', ['major', 'minor']);
            $table->enum('suit', ['wands', 'cups', 'swords', 'pentacles'])->nullable();
            $table->integer('number');
            $table->string('image_path');
            $table->text('upright_meaning');
            $table->text('reversed_meaning');
            $table->json('keywords_upright');
            $table->json('keywords_reversed');
            $table->timestamps();

            $table->index(['deck_id', 'arcana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarot_cards');
    }
};
