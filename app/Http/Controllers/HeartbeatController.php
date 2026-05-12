<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\PlaybackToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeartbeatController extends Controller
{
    public function __invoke(Request $request, Content $content, string $token): JsonResponse
    {
        abort_unless($content->is_active, 404);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['playing', 'paused', 'ended', 'buffering'])],
        ]);

        $status = $validated['status'] ?? 'playing';

        $record = PlaybackToken::query()
            ->where('token', $token)
            ->where('content_id', $content->id)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json(['ok' => false], 404);
        }

        $record->forceFill([
            'last_seen_at' => now(),
            'playback_status' => $status,
        ])->save();

        return response()->json(['ok' => true]);
    }
}
