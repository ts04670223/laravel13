{{-- 牌陣排列元件
    Props: $reading (TarotReading with spread loaded), $cards (Collection keyed by card id)
--}}
@props(['reading', 'cards'])

@php
    $spread = $reading->spread;
    $drawn  = $reading->drawn_cards;
    $count  = $spread->card_count;

    // 排版設定：依張數選擇 grid
    $gridClass = match(true) {
        $count === 1  => 'grid-cols-1 max-w-[120px] mx-auto',
        $count <= 3   => 'grid-cols-3',
        $count <= 5   => 'grid-cols-5',
        $count <= 7   => 'grid-cols-4',
        default       => 'grid-cols-5',
    };
@endphp

<div class="grid {{ $gridClass }} gap-4 justify-items-center">
    @foreach ($drawn as $index => $drawnCard)
        @php
            $card     = $cards[$drawnCard['card_id']] ?? null;
            $position = $spread->positions[$drawnCard['position']] ?? ['name' => '位置' . ($drawnCard['position'] + 1), 'description' => ''];
        @endphp
        @if ($card)
            @include('tarot.partials.card', [
                'drawnCard' => $drawnCard,
                'card'      => $card,
                'position'  => $position,
                'index'     => $index,
            ])
        @endif
    @endforeach
</div>
