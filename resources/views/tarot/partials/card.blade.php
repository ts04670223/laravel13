{{-- 單張牌卡元件
    Props: $drawnCard (array), $card (TarotCard), $position (array), $index (int)
--}}
@props(['drawnCard', 'card', 'position', 'index'])

<div class="flex flex-col items-center gap-2"
     x-data="{ flipped: false }"
     x-init="setTimeout(() => flipped = true, {{ $index * 350 + 400 }})">

    {{-- 翻牌容器 --}}
    <div class="relative cursor-pointer select-none"
         style="width:80px; height:136px; perspective:800px"
         @click="flipped = !flipped"
         title="{{ $card->name_zh }} — {{ $drawnCard['is_reversed'] ? '逆位' : '正位' }}">

        <div class="w-full h-full transition-transform duration-700"
             style="transform-style: preserve-3d; position: relative;"
             :style="flipped ? 'transform: rotateY(0deg)' : 'transform: rotateY(180deg)'">

            {{-- 牌正面 --}}
            <div class="absolute inset-0 rounded-lg overflow-hidden shadow-md"
                 style="backface-visibility: hidden;">
                <img src="{{ asset('images/tarot/rider-waite/' . $card->image_path) }}"
                     alt="{{ $card->name_zh }}"
                     class="w-full h-full object-cover"
                     style="{{ $drawnCard['is_reversed'] ? 'transform: rotate(180deg);' : '' }}"
                     onerror="this.src='{{ asset('images/tarot/card-back.jpg') }}'">
            </div>

            {{-- 牌背面 --}}
            <div class="absolute inset-0 rounded-lg shadow-md flex items-center justify-center"
                 style="backface-visibility: hidden; transform: rotateY(180deg); background: linear-gradient(135deg, #3b0764, #1e1b4b);">
                <span class="text-3xl">🔮</span>
            </div>
        </div>
    </div>

    {{-- 牌名與位置標籤 --}}
    <div class="text-center max-w-[90px]">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $position['name'] }}</p>
        <p class="text-xs font-semibold text-gray-800 leading-tight mt-0.5">{{ $card->name_zh }}</p>
        <p class="text-[10px] mt-0.5 {{ $drawnCard['is_reversed'] ? 'text-red-400' : 'text-green-500' }}">
            {{ $drawnCard['is_reversed'] ? '▼ 逆位' : '▲ 正位' }}
        </p>
    </div>
</div>
