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
        $content = $reading->ai_interpretation;
        $lines = explode("\n", $content);
        $result = [];
        $tableLines = [];
        $inTable = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|')) {
                $inTable = true;
                $tableLines[] = $trimmed;
            } else {
                if ($inTable) {
                    $result[] = $this->buildTable($tableLines);
                    $tableLines = [];
                    $inTable = false;
                }
                $result[] = $line;
            }
        }
        if ($inTable) {
            $result[] = $this->buildTable($tableLines);
        }

        $content = implode("\n", $result);
        // 1. 處理標題 (### -> h4, ## -> h3)
        $content = preg_replace('/### (.*)/', '<h4 class="text-base font-bold mt-4 mb-2">$1</h4>', $content);
        $content = preg_replace('/## (.*)/', '<h3 class="text-lg font-bold mt-6 mb-3">$1</h3>', $content);

        // 2. 處理粗體
        $content = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-bold text-gray-900">$1</strong>', $content);

        // 3. 處理區塊引用 (>)
        $content = preg_replace('/> (.*)/', '<blockquote class="border-l-4 border-gray-300 pl-4 py-1 italic my-3">$1</blockquote>', $content);

        // 4. 處理分隔線 (---)
        $content = str_replace('---', '<hr class="my-6 border-gray-200">', $content);

        // 5. 處理列表 (以 - 開頭的項目)
        // 這裡我們將連續的列表項目用 <ul> 包起來會比較麻煩，
        // 最簡單的做法是將每一行轉為 <li class="my-1">
        $content = preg_replace('/- (.*)/', '<li class="ml-4">$1</li>', $content);

        // 6. 將剩下的純文字段落用 <p> 包裹 (處理換行)
        $content = nl2br($content);
        $reading['ai_interpretation'] = $content;
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

    private function buildTable(array $lines): string
    {
        // 過濾分隔線（|---|---|）
        $rows = array_filter($lines, fn($line) => !preg_match('/^\|[\s\-\|]+\|$/', $line));
        $rows = array_values($rows);

        if (empty($rows)) return '';

        $html = '<table class="w-full border-collapse my-4 text-sm">';

        foreach ($rows as $index => $row) {
            $cells = explode('|', $row);
            // 去掉首尾空元素
            array_shift($cells);
            array_pop($cells);

            $tag = $index === 0 ? 'th' : 'td';
            $class = $index === 0
                ? 'class="border border-gray-300 px-3 py-2 bg-gray-50 font-bold text-left"'
                : 'class="border border-gray-300 px-3 py-2 align-top"';

            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= "<{$tag} {$class}>" . trim($cell) . "</{$tag}>";
            }
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }
}
