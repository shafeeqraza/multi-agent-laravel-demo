<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use App\Http\Responses\ChatEnvelope;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __invoke(ChatRequest $request, ChatService $chatService): JsonResponse
    {
        $message = trim((string) $request->validated('message'));

        $result = $chatService->sendMessage($message);

        // Guest (or model) routed to login before order-history tools run.
        if (! empty($result['auth_required'])) {
            return response()->json([
                'reply' => $result['reply'] ?? 'Please log in first so I can access your recent orders.',
                'auth_required' => true,
            ]);
        }

        return response()->json(ChatEnvelope::success($result));
    }
}
