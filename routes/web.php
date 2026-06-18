<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TarotController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/chat', fn () => view('chat'))->name('chat');
    Route::get('/documents', fn () => view('documents'))->name('documents');

    // Tarot
    Route::get('/tarot', [TarotController::class, 'index'])->name('tarot.index');
    Route::post('/tarot/readings', [TarotController::class, 'store'])->name('tarot.store');
    Route::get('/tarot/readings', [TarotController::class, 'history'])->name('tarot.history');
    Route::get('/tarot/readings/{reading}', [TarotController::class, 'show'])->name('tarot.reading');
});

require __DIR__.'/auth.php';
