<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\RagPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(private RagPipeline $ragPipeline) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        $message = $this->ragPipeline->answer($validated['message'], $conversation);

        return response()->json(['message' => $message]);
    }

    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->stream(function () use ($validated, $conversation) {
            try {
                foreach ($this->ragPipeline->stream($validated['message'], $conversation) as $event) {
                    echo "event: {$event['type']}\n";
                    echo 'data: ' . json_encode($event) . "\n\n";
                    ob_flush();
                    flush();
                }
            } catch (\Throwable $e) {
                $error = ['type' => 'error', 'message' => $e->getMessage()];
                echo "event: error\n";
                echo 'data: ' . json_encode($error) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
