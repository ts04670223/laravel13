<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🃏 占卜結果</h2>
            <a href="{{ route('tarot.index') }}" class="text-sm text-purple-600 hover:text-purple-800 hover:underline transition-colors">
                ← 重新占卜
            </a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6"
         x-data="tarotReading({{ $reading->id }}, {{ $reading->ai_interpretation ? 'true' : 'false' }}, {{ $reading->conversation_id }})">

        {{-- 問題標題 --}}
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $reading->spread->name_zh }}</span>
                <span class="text-gray-200">·</span>
                <span class="text-xs text-gray-400">{{ $reading->reading_style === 'mystic' ? '🌙 神秘靈性' : '🧠 理性分析' }}</span>
                <span class="text-gray-200">·</span>
                <span class="text-xs text-gray-400">{{ $reading->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mt-1">「{{ $reading->question }}」</h3>
        </div>

        {{-- 牌陣展示區 --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-5">抽到的牌</h4>
            @include('tarot.partials.spread', ['reading' => $reading, 'cards' => $cards])
        </div>

        {{-- AI 解讀串流區 --}}
        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">AI 解讀</h4>
                <button x-show="streamError" @click="startStream()"
                    class="text-xs text-purple-600 hover:underline">
                    🔄 重新生成
                </button>
            </div>

            @if ($reading->ai_interpretation)
                {{-- 已有解讀，直接顯示 --}}
                <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $reading->ai_interpretation }}</div>
            @else
                {{-- 串流區 --}}
                <div class="min-h-20">
                    <div x-show="!streamStarted && !streamError" class="flex items-center gap-2 text-gray-400 text-sm">
                        <svg class="animate-spin w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        正在召喚解讀...
                    </div>
                    <div x-show="streamError" class="text-sm text-red-500">
                        ⚠️ 解讀中斷，請點擊右上方「重新生成」
                    </div>
                    <div x-show="streamStarted && !streamError">
                        <div class="prose prose-sm max-w-none text-gray-700 leading-relaxed whitespace-pre-wrap"
                             x-text="streamContent"></div>
                        <span x-show="streaming"
                              class="inline-block w-0.5 h-4 bg-purple-500 ml-0.5 animate-pulse align-middle"></span>
                    </div>
                </div>
            @endif
        </div>

        {{-- 繼續追問區（解讀完成後出現） --}}
        <div x-show="streamDone || {{ $reading->ai_interpretation ? 'true' : 'false' }}"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="bg-white rounded-2xl shadow p-6">

            <h4 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">繼續追問</h4>

            {{-- 對話訊息區 --}}
            <div id="tarot-chat-messages" class="space-y-3 mb-4 max-h-72 overflow-y-auto pr-1"></div>

            {{-- 輸入框 --}}
            <form @submit.prevent="sendChat()" class="flex gap-2">
                <input type="text"
                       x-model="chatInput"
                       :disabled="chatLoading"
                       placeholder="針對占卜結果繼續提問…"
                       class="flex-1 rounded-xl border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500 disabled:opacity-50">
                <button type="submit"
                        :disabled="chatLoading || !chatInput.trim()"
                        class="px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-medium hover:bg-purple-700 disabled:opacity-40 transition-colors">
                    <span x-show="!chatLoading">送出</span>
                    <svg x-show="chatLoading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </button>
            </form>
        </div>

    </div>

    @push('scripts')
    <script>
    function tarotReading(readingId, alreadyInterpreted, conversationId) {
        return {
            streamContent: '',
            streamStarted: false,
            streamDone: alreadyInterpreted,
            streaming: false,
            streamError: false,
            chatInput: '',
            chatLoading: false,

            init() {
                if (!alreadyInterpreted) {
                    this.startStream();
                }
            },

            async startStream() {
                this.streamError   = false;
                this.streamStarted = true;
                this.streaming     = true;
                this.streamContent = '';

                try {
                    const res = await fetch('/api/tarot/readings/' + readingId + '/stream', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'text/event-stream',
                        },
                    });

                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    const reader  = res.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer    = '';

                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop();

                        for (const line of lines) {
                            if (!line.startsWith('data: ')) continue;
                            let data;
                            try { data = JSON.parse(line.slice(6)); } catch { continue; }

                            if (data.content)      this.streamContent += data.content;
                            if (data.type === 'done') {
                                this.streaming  = false;
                                this.streamDone = true;
                            }
                            if (data.type === 'error') {
                                this.streaming   = false;
                                this.streamError = true;
                            }
                        }
                    }
                } catch (e) {
                    this.streaming   = false;
                    this.streamError = true;
                }
            },

            async sendChat() {
                const msg = this.chatInput.trim();
                if (!msg) return;

                this.chatInput  = '';
                this.chatLoading = true;

                const container = document.getElementById('tarot-chat-messages');

                // 用戶訊息泡泡
                const userEl = document.createElement('div');
                userEl.className = 'flex justify-end';
                userEl.innerHTML = `<span class="bg-purple-50 text-purple-900 text-sm px-4 py-2 rounded-2xl rounded-tr-sm max-w-xs break-words">${this.escapeHtml(msg)}</span>`;
                container.appendChild(userEl);

                // AI 回應泡泡（串流填入）
                const replyEl   = document.createElement('div');
                replyEl.className = 'flex justify-start';
                const replySpan = document.createElement('span');
                replySpan.className = 'bg-gray-100 text-gray-800 text-sm px-4 py-2 rounded-2xl rounded-tl-sm max-w-xs break-words whitespace-pre-wrap';
                replySpan.textContent = '';
                replyEl.appendChild(replySpan);
                container.appendChild(replyEl);
                container.scrollTop = container.scrollHeight;

                try {
                    const res = await fetch('/api/chat/stream', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ conversation_id: conversationId, message: msg }),
                    });

                    const reader  = res.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer    = '';

                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop();

                        for (const line of lines) {
                            if (!line.startsWith('data: ')) continue;
                            let data;
                            try { data = JSON.parse(line.slice(6)); } catch { continue; }
                            if (data.content) {
                                replySpan.textContent += data.content;
                                container.scrollTop = container.scrollHeight;
                            }
                        }
                    }
                } catch (e) {
                    replySpan.textContent = '發送失敗，請稍後再試。';
                }

                this.chatLoading = false;
            },

            escapeHtml(str) {
                return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
