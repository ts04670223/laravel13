<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarot_spreads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_zh');
            $table->text('description');
            $table->integer('card_count');
            $table->json('positions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarot_spreads');
    }
};
