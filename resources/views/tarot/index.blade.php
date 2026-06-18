<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">🔮 塔羅占卜</h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- 占卜表單 --}}
            <div class="lg:col-span-2 bg-white rounded-2xl shadow p-6" x-data="{ style: 'mystic', loading: false }">

                <form action="{{ route('tarot.store') }}" method="POST" @submit="loading = true">
                    @csrf

                    {{-- 風格選擇 --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">解讀風格</label>
                        <div class="flex gap-3">
                            <button type="button" @click="style = 'mystic'"
                                :class="style === 'mystic'
                                    ?
                                    'bg-purple-600 !text-white shadow-md' :
                                    'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="flex-1 py-2.5 px-4 rounded-xl text-sm font-medium transition-all duration-200">
                                🌙 神秘靈性
                            </button>
                            {{-- <button type="button"
                                @click="style = 'mystic'"
                                :class="style === 'mystic'
                                    ? 'bg-purple-600 text-white shadow-md'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="flex-1 py-2.5 px-4 rounded-xl text-sm font-medium transition-all duration-200">
                                🌙 神秘靈性
                            </button> --}}
                            <button type="button" @click="style = 'rational'"
                                :class="style === 'rational'
                                    ?
                                    'bg-blue-600 text-white shadow-md' :
                                    'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="flex-1 py-2.5 px-4 rounded-xl text-sm font-medium transition-all duration-200">
                                🧠 理性分析
                            </button>
                        </div>
                        <input type="hidden" name="reading_style" :value="style">
                    </div>

                    {{-- 問題輸入 --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">你想占卜的問題</label>
                        <textarea name="question" rows="4"
                            class="w-full rounded-xl border-gray-300 focus:ring-purple-500 focus:border-purple-500 text-sm resize-none"
                            placeholder="例如：我目前的感情狀況如何？工作上的轉換時機到了嗎？" required maxlength="500" :class="loading ? 'opacity-50' : ''">{{ old('question') }}</textarea>
                        @error('question')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" :disabled="loading"
                        class="w-full py-3 px-6 rounded-xl font-medium transition-all duration-200 disabled:opacity-60 text-white"
                        :class="style === 'mystic'
                            ?
                            'bg-purple-700 hover:bg-purple-800' :
                            'bg-blue-600 hover:bg-blue-700'">

                        <span x-show="!loading">✨ 開始占卜</span>

                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            AI 正在選擇牌陣...
                        </span>
                    </button>
                </form>
            </div>

            {{-- 歷史記錄側欄 --}}
            <div class="bg-white rounded-2xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">最近占卜</h3>
                    @if ($recentReadings->count() >= 10)
                        <a href="{{ route('tarot.history') }}" class="text-xs text-purple-600 hover:underline">全部 →</a>
                    @endif
                </div>

                @forelse ($recentReadings as $r)
                    <a href="{{ route('tarot.reading', $r) }}"
                        class="block py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50 -mx-2 px-2 rounded-lg transition-colors">
                        <p class="text-sm text-gray-800 truncate">{{ $r->question }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $r->spread->name_zh }} ·
                            {{ $r->reading_style === 'mystic' ? '🌙' : '🧠' }} ·
                            {{ $r->created_at->diffForHumans() }}
                        </p>
                    </a>
                @empty
                    <div class="text-center py-8">
                        <p class="text-3xl mb-2">🃏</p>
                        <p class="text-sm text-gray-400">尚無占卜記錄</p>
                        <p class="text-xs text-gray-300 mt-1">輸入問題開始你的第一次占卜</p>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
