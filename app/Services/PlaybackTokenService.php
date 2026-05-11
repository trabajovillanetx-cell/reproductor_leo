<?php

namespace App\Services;

use App\Models\Content;
use App\Models\PlaybackToken;
use App\Models\User;
use Illuminate\Support\Str;

class PlaybackTokenService
{
    public function create(User $user, Content $content, ?int $customerProfileId = null, ?int $ttlMinutesOverride = null): PlaybackToken
    {
        $ttl = $ttlMinutesOverride !== null
            ? max(1, $ttlMinutesOverride)
            : max(1, (int) config('streaming.playback_token_ttl_minutes', 5));

        return PlaybackToken::create([
            'user_id' => $user->id,
            'customer_profile_id' => $customerProfileId,
            'content_id' => $content->id,
            'token' => Str::random(48),
            'expires_at' => now()->addMinutes($ttl),
        ]);
    }

    public function findValid(string $token, int $contentId): ?PlaybackToken
    {
        return PlaybackToken::query()
            ->where('token', $token)
            ->where('content_id', $contentId)
            ->where('expires_at', '>', now())
            ->first();
    }
}
