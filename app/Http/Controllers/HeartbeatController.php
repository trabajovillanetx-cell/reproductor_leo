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
            'status'   => ['sometimes', 'string', Rule::in(['playing', 'paused', 'ended', 'buffering'])],
            'position' => ['sometimes', 'numeric', 'min:0'],
            'duration' => ['sometimes', 'numeric', 'min:0'],
        ]);
        $status = $validated['status'] ?? 'playing';
        $position = isset($validated['position']) ? (int) $validated['position'] : null;
        $duration = isset($validated['duration']) ? (int) $validated['duration'] : null;
        $record = PlaybackToken::query()
            ->where('token', $token)
            ->where('content_id', $content->id)
            ->where('expires_at', '>', now())
            ->first();
        if (! $record) {
            return response()->json(['ok' => false], 404);
        }
        // Leer comando pendiente del admin
        $command = $record->admin_command;
        $commandData = $record->admin_command_data;
        // Solo limpiar el comando en heartbeats del intervalo (no en eventos inmediatos)
        $isIntervalHeartbeat = (bool) ($request->input('interval', false));
        $fill = [
            'last_seen_at' => now(),
            'playback_status' => $status,
            'admin_command' => $isIntervalHeartbeat ? null : $record->admin_command,
            'admin_command_data' => $isIntervalHeartbeat ? null : $record->admin_command_data,
        ];
        if ($position !== null) $fill['position_seconds'] = $position;
        if ($duration !== null) $fill['duration_seconds'] = $duration;
        $record->forceFill($fill)->save();
        $response = ['ok' => true];
        if ($command) {
            $response['command'] = $command;
            if ($commandData) {
                $response['command_data'] = $commandData;
            }
        }
        return response()->json($response);
    }
}
