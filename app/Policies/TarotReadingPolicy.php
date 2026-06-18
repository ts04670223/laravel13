<?php

namespace App\Policies;

use App\Models\TarotReading;
use App\Models\User;

class TarotReadingPolicy
{
    public function view(User $user, TarotReading $reading): bool
    {
        return $user->id === $reading->user_id;
    }

    public function stream(User $user, TarotReading $reading): bool
    {
        return $user->id === $reading->user_id;
    }
}
