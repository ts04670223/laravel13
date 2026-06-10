<x-app-layout>
    <style>
        /* ── Custom tokens ── */
        :root {
            --sidebar-bg: #0f1117;
            --sidebar-hover: rgba(255, 255, 255, 0.07);
            --sidebar-active: rgba(99, 139, 255, 0.18);
            --sidebar-active-text: #a8bfff;
            --sidebar-text: #9ca3af;
            --accent-blue: #638bff;
            --accent-purple: #9b72cb;
            --accent-rose: #d96570;
            --surface: #ffffff;
            --surface-muted: #f7f8fc;
            --border: #e8eaf0;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --user-bubble: #f0f3ff;
            --input-bg: #f4f6fb;
        }

        /* ── Sidebar scrollbar ── */
        #sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }

        #sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        #sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 4px;
        }

        /* ── Messages scrollbar ── */
        #messages-container::-webkit-scrollbar {
            width: 5px;
        }

        #messages-container::-webkit-scrollbar-track {
            background: transparent;
        }

        #messages-container::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        /* ── Prose styles for assistant messages ── */
        .gemini-prose p {
            margin-bottom: 0.75em;
        }

        .gemini-prose p:last-child {
            margin-bottom: 0;
        }

        .gemini-prose ul {
            list-style: disc;
            padding-left: 1.5em;
            margin-bottom: 0.75em;
        }

        .gemini-prose ol {
            list-style: decimal;
            padding-left: 1.5em;
            margin-bottom: 0.75em;
        }

        .gemini-prose li {
            margin-bottom: 0.25em;
        }

        .gemini-prose code {
            background: #eef0f8;
            padding: 0.15em 0.45em;
            border-radius: 5px;
            font-size: 0.88em;
        }

        .gemini-prose pre {
            background: #1e2030;
            color: #cdd6f4;
            padding: 1em 1.25em;
            border-radius: 12px;
            overflow-x: auto;
            margin-bottom: 0.75em;
            font-size: 0.9em;
        }

        .gemini-prose pre code {
            background: transparent;
            padding: 0;
            color: inherit;
        }

        .gemini-prose strong {
            font-weight: 600;
            color: var(--text-primary);
        }

        .gemini-prose h2,
        .gemini-prose h3 {
            font-weight: 600;
            margin-top: 1.25em;
            margin-bottom: 0.5em;
        }

        /* ── Gem icon spin ── */
        @keyframes spin-slow {
            to {
                transform: rotate(360deg);
            }
        }

        .animate-spin-slow {
            animation: spin-slow 2s linear infinite;
        }

        /* ── Welcome gradient title ── */
        .welcome-gradient {
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-purple) 50%, var(--accent-rose) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Input focus ring ── */
        .chat-input-wrap:focus-within {
            background: #fff;
            box-shadow: 0 0 0 2px rgba(99, 139, 255, 0.25), 0 4px 16px rgba(0, 0, 0, 0.07);
        }

        /* ── New conversation button shine ── */
        .new-conv-btn {
            background: linear-gradient(135deg, rgba(99, 139, 255, 0.15), rgba(155, 114, 203, 0.12));
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: background 0.2s, box-shadow 0.2s;
        }

        .new-conv-btn:hover {
            background: linear-gradient(135deg, rgba(99, 139, 255, 0.28), rgba(155, 114, 203, 0.22));
            box-shadow: 0 2px 12px rgba(99, 139, 255, 0.2);
        }
    </style>

    <div class="flex bg-white" style="height:calc(100vh - 65px)" x-data="chatApp">

        {{-- ── Sidebar ── --}}
        <div class="flex flex-col w-64 shrink-0" style="background:var(--sidebar-bg)">

            {{-- New conversation --}}
            <div class="p-4 pb-2">
                <button @click="createConversation()"
                    class="new-conv-btn flex items-center gap-2.5 w-full pl-4 pr-5 py-3 rounded-2xl text-white text-[14px] font-medium">
                    <svg class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    新對話
                </button>
            </div>

            {{-- Conversation list --}}
            <div class="flex-1 overflow-y-auto px-3 pb-4 mt-2" id="sidebar-scroll">
                <p class="px-2 mb-1.5 text-[11px] font-semibold uppercase tracking-widest"
                    style="color:rgba(255,255,255,0.28)">近期</p>

                <template x-for="conv in conversations" :key="conv.id">
                    <div @click="selectConversation(conv)"
                        :style="currentConversation?.id === conv.id ?
                            'background:var(--sidebar-active);color:var(--sidebar-active-text)' :
                            'color:var(--sidebar-text)'"
                        class="group flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl cursor-pointer transition-colors duration-150 mt-0.5"
                        style="transition:background 0.15s"
                        :class="currentConversation?.id !== conv.id ? 'hover:bg-[rgba(255,255,255,0.07)]' : ''">

                        <div class="flex items-center gap-2.5 min-w-0">
                            <svg class="w-3.5 h-3.5 shrink-0 opacity-50" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.84L3 20l1.04-3.12A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <span x-text="conv.title || '新對話'" class="truncate text-[13.5px]"></span>
                        </div>

                        <button @click.stop="deleteConversation(conv.id)"
                            class="shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded-lg transition-all"
                            style="color:rgba(255,255,255,0.4)"
                            onmouseover="this.style.color='#f87171';this.style.background='rgba(239,68,68,0.15)'"
                            onmouseout="this.style.color='rgba(255,255,255,0.4)';this.style.background='transparent'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── Main chat area ── --}}
        <div class="flex-1 flex flex-col overflow-hidden relative" style="background:var(--surface)">

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto" id="messages-container">

                {{-- Welcome state --}}
                <div x-show="messages.length === 0 && !isStreaming"
                    class="h-full flex flex-col justify-center max-w-3xl mx-auto w-full px-6 sm:px-8">
                    <div class="mb-4">
                        {{-- Gem icon, larger --}}
                        <div class="mb-7">
                            <svg viewBox="0 0 36 36" class="w-12 h-12">
                                <defs>
                                    <linearGradient id="gem-welcome" x1="0" y1="0" x2="1"
                                        y2="1">
                                        <stop offset="0%" stop-color="#638bff" />
                                        <stop offset="50%" stop-color="#9b72cb" />
                                        <stop offset="100%" stop-color="#d96570" />
                                    </linearGradient>
                                </defs>
                                <path fill="url(#gem-welcome)"
                                    d="M18 3c.45 5.4 3.6 9.45 9 9.75-5.4.45-9.45 3.6-9.75 9-.45-5.4-3.6-9.45-9-9.75 5.4-.45 9.45-3.6 9.75-9z" />
                            </svg>
                        </div>
                        <h1
                            class="welcome-gradient text-[38px] sm:text-[52px] font-semibold tracking-tight leading-[1.15] pb-1">
                            哈囉，{{ Str::of(auth()->user()->name)->before(' ') }}
                        </h1>
                        <p class="text-[38px] sm:text-[52px] font-semibold tracking-tight leading-[1.15]"
                            style="color:#c9ccd4">今天能為你做什麼？</p>
                    </div>
                    {{-- Subtle hint --}}
                    <p class="mt-6 text-[14px]" style="color:var(--text-secondary)">
                        輸入任何問題，或從左側選擇過去的對話
                    </p>
                </div>

                {{-- Message list --}}
                <div x-show="messages.length > 0 || isStreaming" class="max-w-3xl mx-auto px-6 sm:px-8 py-10 space-y-6">

                    <template x-for="msg in messages" :key="msg.id">
                        <div>
                            {{-- User message --}}
                            <template x-if="msg.role === 'user'">
                                <div class="flex justify-end mt-4">
                                    <div class="rounded-2xl px-5 py-3 max-w-[78%] text-[15px] leading-[1.65] shadow-sm"
                                        style="background:var(--user-bubble);color:var(--text-primary)">
                                        <div x-text="msg.content" class="break-words whitespace-pre-wrap"></div>
                                    </div>
                                </div>
                            </template>

                            {{-- Assistant message --}}
                            <template x-if="msg.role !== 'user'">
                                <div class="flex gap-3.5 mt-6">
                                    <div class="shrink-0 w-7 h-7 mt-0.5">
                                        <svg viewBox="0 0 24 24" class="w-7 h-7">
                                            <defs>
                                                <linearGradient id="gem-msg" x1="0" y1="0"
                                                    x2="1" y2="1">
                                                    <stop offset="0%" stop-color="#638bff" />
                                                    <stop offset="50%" stop-color="#9b72cb" />
                                                    <stop offset="100%" stop-color="#d96570" />
                                                </linearGradient>
                                            </defs>
                                            <path fill="url(#gem-msg)"
                                                d="M12 2c.3 3.6 2.4 6.3 6 6.5-3.6.3-6.3 2.4-6.5 6-.3-3.6-2.4-6.3-6-6.5 3.6-.3 6.3-2.4 6.5-6z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-0.5 overflow-x-auto">
                                        <div x-html="renderMarkdown(msg.content)"
                                            class="gemini-prose text-[15px] leading-[1.75] break-words"
                                            style="color:var(--text-primary)"></div>

                                        {{-- Sources --}}
                                        <template x-if="msg.sources && msg.sources.length > 0">
                                            <div class="mt-4 space-y-2">
                                                <p class="text-[11px] font-semibold uppercase tracking-widest"
                                                    style="color:var(--text-secondary)">引用來源</p>
                                                <template x-for="source in msg.sources" :key="source.chunk_id">
                                                    <div class="text-[12.5px] rounded-xl p-3 border"
                                                        style="background:var(--surface-muted);border-color:var(--border)">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="font-medium truncate"
                                                                style="color:var(--text-primary)"
                                                                x-text="source.document_title"></span>
                                                            <span
                                                                class="shrink-0 text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                                                style="background:rgba(99,139,255,0.1);color:var(--accent-blue)"
                                                                x-text="(source.score * 100).toFixed(0) + '%'"></span>
                                                        </div>
                                                        <p class="mt-1 line-clamp-2"
                                                            style="color:var(--text-secondary)"
                                                            x-text="source.content_preview"></p>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Streaming indicator --}}
                    <div x-show="isStreaming" class="flex gap-3.5 pb-4">
                        <div class="shrink-0 w-7 h-7 mt-0.5">
                            <svg viewBox="0 0 24 24" class="w-7 h-7"
                                :class="!streamingContent ? 'animate-spin-slow' : ''">
                                <path fill="url(#gem-msg)"
                                    d="M12 2c.3 3.6 2.4 6.3 6 6.5-3.6.3-6.3 2.4-6.5 6-.3-3.6-2.4-6.3-6-6.5 3.6-.3 6.3-2.4 6.5-6z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5 overflow-x-auto">
                            <template x-if="streamingContent">
                                <div x-html="renderMarkdown(streamingContent)"
                                    class="gemini-prose text-[15px] leading-[1.75] break-words"
                                    style="color:var(--text-primary)"></div>
                            </template>
                            <template x-if="!streamingContent">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full animate-bounce"
                                        style="background:var(--accent-blue);animation-delay:0ms"></span>
                                    <span class="w-1.5 h-1.5 rounded-full animate-bounce"
                                        style="background:var(--accent-purple);animation-delay:150ms"></span>
                                    <span class="w-1.5 h-1.5 rounded-full animate-bounce"
                                        style="background:var(--accent-rose);animation-delay:300ms"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Input bar ── --}}
            <div class="px-4 pb-5 pt-3 max-w-3xl mx-auto w-full">
                <form @submit.prevent="sendMessage()"
                    class="chat-input-wrap flex items-end gap-2 rounded-2xl px-5 py-3 transition-all duration-200"
                    style="background:var(--input-bg);border:1.5px solid var(--border)">
                    <input x-model="inputMessage" :disabled="isStreaming" type="text" placeholder="傳送訊息…"
                        class="flex-1 bg-transparent border-0 focus:ring-0 text-[15px] leading-relaxed p-0 h-9 self-center placeholder-gray-400"
                        style="color:var(--text-primary)">
                    <button type="submit" :disabled="isStreaming || !inputMessage.trim()"
                        class="shrink-0 w-9 h-9 flex items-center justify-center rounded-xl transition-all duration-150 self-center disabled:opacity-30"
                        :style="inputMessage.trim() && !isStreaming ?
                            'background:linear-gradient(135deg,#638bff,#9b72cb);color:#fff;box-shadow:0 2px 8px rgba(99,139,255,0.35)' :
                            'background:transparent;color:#9ca3af'">
                        <svg class="w-4 h-4 relative right-px" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 20v-6l8-2-8-2V4l19 8z" />
                        </svg>
                    </button>
                </form>
                <p class="text-center text-[11px] mt-2" style="color:#c4c7cc">
                    AI 可能會出錯，請自行確認重要資訊
                </p>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('chatApp', () => ({
                    conversations: [],
                    currentConversation: null,
                    messages: [],
                    inputMessage: '',
                    isStreaming: false,
                    streamingContent: '',

                    async init() {
                        await this.loadConversations();
                    },

                    async loadConversations() {
                        const res = await fetch('/api/conversations');
                        const data = await res.json();
                        this.conversations = data.data || data;
                    },

                    async createConversation() {
                        const res = await fetch('/api/conversations', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')
                                    .content
                            },
                            body: JSON.stringify({
                                title: null
                            }),
                        });
                        const conv = await res.json();
                        this.conversations.unshift(conv);
                        this.selectConversation(conv);
                    },

                    async selectConversation(conv) {
                        this.currentConversation = conv;
                        const res = await fetch(`/api/conversations/${conv.id}`);
                        const data = await res.json();
                        this.messages = data.messages || [];
                    },

                    async deleteConversation(id) {
                        if (!confirm('確定要刪除這個對話嗎？')) return;
                        await fetch(`/api/conversations/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')
                                    .content
                            },
                        });
                        this.conversations = this.conversations.filter(c => c.id !== id);
                        if (this.currentConversation?.id === id) {
                            this.currentConversation = null;
                            this.messages = [];
                        }
                    },

                    async sendMessage() {
                        if (!this.inputMessage.trim()) return;

                        if (!this.currentConversation) {
                            await this.createConversation();
                            if (!this.currentConversation) return;
                        }

                        const question = this.inputMessage;
                        this.inputMessage = '';
                        this.messages.push({
                            id: Date.now(),
                            role: 'user',
                            content: question,
                            sources: null
                        });
                        this.isStreaming = true;
                        this.streamingContent = '';

                        try {
                            const response = await fetch('/api/chat/stream', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name=csrf-token]').content
                                },
                                body: JSON.stringify({
                                    conversation_id: this.currentConversation.id,
                                    message: question
                                }),
                            });

                            const reader = response.body.getReader();
                            const decoder = new TextDecoder();
                            let sources = null;
                            let buffer = '';

                            while (true) {
                                const {
                                    done,
                                    value
                                } = await reader.read();
                                if (done) break;

                                buffer += decoder.decode(value, {
                                    stream: true
                                });
                                const lines = buffer.split('\n');
                                buffer = lines.pop();

                                for (const line of lines) {
                                    if (line.startsWith('data: ')) {
                                        let data;
                                        try {
                                            data = JSON.parse(line.slice(6));
                                        } catch (e) {
                                            continue;
                                        }
                                        if (data.type === 'chunk') this.streamingContent += data
                                        .content;
                                        else if (data.type === 'sources') sources = data.sources;
                                        else if (data.type === 'error') this.streamingContent +=
                                            '\n\n⚠️ ' + (data.message || '發生錯誤');
                                    }
                                }

                                this.$nextTick(() => {
                                    const el = document.getElementById('messages-container');
                                    el.scrollTop = el.scrollHeight;
                                });
                            }

                            this.messages.push({
                                id: Date.now(),
                                role: 'assistant',
                                content: this.streamingContent,
                                sources: sources
                            });
                        } catch (e) {
                            this.messages.push({
                                id: Date.now(),
                                role: 'assistant',
                                content: '發生錯誤，請稍後再試。',
                                sources: null
                            });
                        }

                        this.isStreaming = false;
                        this.streamingContent = '';
                    },

                    renderMarkdown(text) {
                        if (!text) return '';
                        return text
                            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.*?)\*/g, '<em>$1</em>')
                            .replace(/`(.*?)`/g, '<code>$1</code>')
                            .replace(/\n/g, '<br>');
                    }
                }))
            });
        </script>
    @endpush
</x-app-layout>
