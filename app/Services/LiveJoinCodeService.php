<?php

namespace App\Services;

use App\Models\LiveQuiz;
use App\Models\LiveQuizSession;
use Illuminate\Support\Str;

class LiveJoinCodeService
{
    public static function generate(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::isTaken($code));

        return $code;
    }

    public static function isTaken(string $code): bool
    {
        $normalized = strtoupper($code);

        return LiveQuiz::where('join_code', $normalized)->exists()
            || LiveQuizSession::where('join_code', $normalized)->exists();
    }
}
