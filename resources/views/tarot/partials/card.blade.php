{{-- 單張牌卡元件 --}}
@props(['drawnCard', 'card', 'position', 'index'])

<div class="flex flex-col items-center gap-2" x-data="{ flipped: false }" x-init="setTimeout(() => flipped = true, {{ $index * 350 + 400 }})">

    {{-- 翻牌容器 --}}
    <div class="group relative w-20 h-[136px] [perspective:800px] cursor-pointer select-none" @click="flipped = !flipped"
        title="{{ $card->name_zh }} — {{ $drawnCard['is_reversed'] ? '逆位' : '正位' }}">

        <div class="relative w-full h-full transition-transform duration-700 [transform-style:preserve-3d]"
            :class="flipped ? '[transform:rotateY(0deg)]' : '[transform:rotateY(180deg)]'">

            {{-- 牌正面 --}}
            <div>
                <img src="{{ asset('images/tarot/rider-waite/' . $card->image_path) }}" alt="{{ $card->name_zh }}"
                    class="w-full h-full object-cover {{ $drawnCard['is_reversed'] ? 'rotate-180' : '' }}"
                    onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.classList.replace('hidden', 'flex');">

                {{-- 圖片載入失敗時的 placeholder --}}
                <div
                    class="hidden flex-col items-center justify-center w-full h-full bg-gradient-to-br from-indigo-900 to-purple-900 p-2">
                    <span class="text-2xl">🃏</span>
                    <span
                        class="text-[9px] text-purple-300 text-center mt-1 leading-tight break-all">{{ $card->name_zh }}</span>
                </div>
            </div>

            {{-- 牌背面 --}}
            <div
                class="absolute inset-0 rounded-lg shadow-md flex items-center justify-center bg-gradient-to-br from-purple-900 to-indigo-900 [backface-visibility:hidden] [transform:rotateY(180deg)]">
                {{-- <span class="text-3xl opacity-80 group-hover:scale-110 transition-transform">🔮</span> --}}
            </div>
        </div>
    </div>

    {{-- 牌名與位置資訊 --}}
    <div class="text-center max-w-[90px] space-y-0.5">
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ $position['name'] }}</p>
        <p class="text-xs font-bold text-gray-800">{{ $card->name_zh }}</p>
        <p class="text-[10px] font-medium {{ $drawnCard['is_reversed'] ? 'text-red-400' : 'text-emerald-500' }}">
            {{ $drawnCard['is_reversed'] ? '▼ 逆位' : '▲ 正位' }}
        </p>
    </div>
</div>
