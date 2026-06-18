<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\TarotCard;
use App\Models\TarotDeck;
use App\Models\TarotReading;
use App\Services\TarotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TarotController extends Controller
{
    public function __construct(private TarotService $tarotService) {}

    public function index(Request $request): View
    {
        $recentReadings = TarotReading::where('user_id', $request->user()->id)
            ->with('spread')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return view('tarot.index', compact('recentReadings'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question'      => 'required|string|max:500',
            'reading_style' => 'required|in:mystic,rational',
        ]);

        $deck = TarotDeck::where('is_active', true)->firstOrFail();

        $spread     = $this->tarotService->selectSpread($validated['question']);
        $drawnCards = $this->tarotService->drawCards($spread, $deck->id);

        $conversation = Conversation::create([
            'user_id' => $request->user()->id,
            'title'   => mb_substr($validated['question'], 0, 50),
            'type'    => 'tarot',
        ]);

        $reading = TarotReading::create([
            'user_id'         => $request->user()->id,
            'conversation_id' => $conversation->id,
            'spread_id'       => $spread->id,
            'question'        => $validated['question'],
            'reading_style'   => $validated['reading_style'],
            'drawn_cards'     => $drawnCards,
        ]);

        return redirect()->route('tarot.reading', $reading);
    }

    public function history(Request $request): View
    {
        $readings = TarotReading::where('user_id', $request->user()->id)
            ->with('spread')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('tarot.history', compact('readings'));
    }

    public function show(Request $request, TarotReading $reading): View
    {
        Gate::authorize('view', $reading);

        $reading->load(['spread', 'conversation']);

        $cardIds = array_column($reading->drawn_cards, 'card_id');
        $cards   = TarotCard::whereIn('id', $cardIds)->get()->keyBy('id');

        return view('tarot.reading', compact('reading', 'cards'));
    }

    public function stream(Request $request, TarotReading $reading): StreamedResponse
    {
        Gate::authorize('stream', $reading);

        return response()->stream(function () use ($reading) {
            try {
                $fullContent = '';

                foreach ($this->tarotService->interpret($reading) as $chunk) {
                    $fullContent .= $chunk;
                    echo "event: chunk\n";
                    echo 'data: ' . json_encode(['content' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                }

                // 串流完成：儲存解讀文字 + 建立第一條 assistant Message
                $reading->update(['ai_interpretation' => $fullContent]);

                Message::create([
                    'conversation_id' => $reading->conversation_id,
                    'role'            => 'assistant',
                    'content'         => $fullContent,
                ]);

                echo "event: done\n";
                echo 'data: ' . json_encode(['type' => 'done']) . "\n\n";
                ob_flush();
                flush();

            } catch (\Throwable $e) {
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
