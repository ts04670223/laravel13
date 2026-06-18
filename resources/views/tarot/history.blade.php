<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">📚 占卜歷史</h2>
            <a href="{{ route('tarot.index') }}" class="text-sm text-purple-600 hover:text-purple-800 hover:underline transition-colors">
                ← 新占卜
            </a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-2xl shadow overflow-hidden">
            @forelse ($readings as $r)
                <a href="{{ route('tarot.reading', $r) }}"
                   class="flex items-center gap-4 px-6 py-4 border-b border-gray-100 last:border-0 hover:bg-gray-50 transition-colors group">

                    {{-- 風格圖示 --}}
                    <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-lg
                                {{ $r->reading_style === 'mystic' ? 'bg-purple-50' : 'bg-blue-50' }}">
                        {{ $r->reading_style === 'mystic' ? '🌙' : '🧠' }}
                    </div>

                    {{-- 問題與牌陣資訊 --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate group-hover:text-purple-700 transition-colors">
                            {{ $r->question }}
                        </p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-gray-400">{{ $r->spread->name_zh }}</span>
                            <span class="text-gray-200 text-xs">·</span>
                            <span class="text-xs text-gray-400">{{ $r->spread->card_count }} 張牌</span>
                            <span class="text-gray-200 text-xs">·</span>
                            <span class="text-xs text-gray-400">{{ $r->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>

                    {{-- 解讀狀態標籤 --}}
                    <div class="shrink-0">
                        @if ($r->ai_interpretation)
                            <span class="text-xs px-2.5 py-1 rounded-full bg-green-50 text-green-600 font-medium">已解讀</span>
                        @else
                            <span class="text-xs px-2.5 py-1 rounded-full bg-yellow-50 text-yellow-600 font-medium">待解讀</span>
                        @endif
                    </div>

                    {{-- 箭頭 --}}
                    <svg class="shrink-0 w-4 h-4 text-gray-300 group-hover:text-purple-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @empty
                <div class="py-16 text-center">
                    <p class="text-4xl mb-3">🃏</p>
                    <p class="text-gray-500 font-medium">尚無占卜記錄</p>
                    <p class="text-sm text-gray-400 mt-1">開始你的第一次塔羅占卜吧</p>
                    <a href="{{ route('tarot.index') }}"
                       class="inline-block mt-4 px-5 py-2 bg-purple-600 text-white text-sm font-medium rounded-xl hover:bg-purple-700 transition-colors">
                        立即占卜
                    </a>
                </div>
            @endforelse
        </div>

        {{-- 分頁 --}}
        @if ($readings->hasPages())
            <div class="mt-4">
                {{ $readings->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
