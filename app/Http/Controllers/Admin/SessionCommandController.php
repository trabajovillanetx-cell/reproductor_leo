<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\PlaybackToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class SessionCommandController extends Controller
{
    public function send(Request $request, int $tokenId): JsonResponse
    {
        $this->authorize('viewAny', Content::class);
        $data = $request->validate([
            'command' => ['required', 'string', 'in:pause,stop,kick,message'],
            'message' => ['nullable', 'string', 'max:200'],
        ]);
        $token = PlaybackToken::findOrFail($tokenId);
        $commandData = null;
        if ($data['command'] === 'message' && !empty($data['message'])) {
            $commandData = $data['message'];
        }
        $token->forceFill([
            'admin_command' => $data['command'],
            'admin_command_data' => $commandData,
        ])->save();
        return response()->json(['ok' => true]);
    }
}
