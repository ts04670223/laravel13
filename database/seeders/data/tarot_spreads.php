<?php

return [
    [
        'name'        => 'Single Card',
        'name_zh'     => '單牌指引',
        'description' => '快速獲得一個核心訊息或建議',
        'card_count'  => 1,
        'positions'   => [
            ['index' => 0, 'name' => '核心訊息', 'description' => '針對問題的直接指引'],
        ],
    ],
    [
        'name'        => 'Three Card Spread',
        'name_zh'     => '三牌展開',
        'description' => '從過去、現在、未來三個面向解析問題',
        'card_count'  => 3,
        'positions'   => [
            ['index' => 0, 'name' => '過去', 'description' => '影響現在的過去因素'],
            ['index' => 1, 'name' => '現在', 'description' => '當前的處境與核心問題'],
            ['index' => 2, 'name' => '未來', 'description' => '可能的發展方向'],
        ],
    ],
    [
        'name'        => 'Five Card Spread',
        'name_zh'     => '五牌展開',
        'description' => '深入分析情況、障礙、建議、潛力與結果',
        'card_count'  => 5,
        'positions'   => [
            ['index' => 0, 'name' => '情況', 'description' => '當前整體狀況'],
            ['index' => 1, 'name' => '障礙', 'description' => '面臨的挑戰或阻力'],
            ['index' => 2, 'name' => '建議', 'description' => '推薦的行動方向'],
            ['index' => 3, 'name' => '潛力', 'description' => '尚未發揮的可能性'],
            ['index' => 4, 'name' => '結果', 'description' => '照此路徑可能的結果'],
        ],
    ],
    [
        'name'        => 'Celtic Cross',
        'name_zh'     => '凱爾特十字',
        'description' => '完整深度占卜，涵蓋問題的所有面向',
        'card_count'  => 10,
        'positions'   => [
            ['index' => 0, 'name' => '核心',     'description' => '問題的核心本質'],
            ['index' => 1, 'name' => '交叉',     'description' => '阻礙或影響核心的力量'],
            ['index' => 2, 'name' => '根基',     'description' => '潛意識的根源'],
            ['index' => 3, 'name' => '過去',     'description' => '已過去的影響'],
            ['index' => 4, 'name' => '王冠',     'description' => '可能的最佳結果'],
            ['index' => 5, 'name' => '未來',     'description' => '即將到來的影響'],
            ['index' => 6, 'name' => '自我',     'description' => '求問者的狀態與態度'],
            ['index' => 7, 'name' => '環境',     'description' => '外在環境與他人看法'],
            ['index' => 8, 'name' => '希望與恐懼', 'description' => '內心的希望或恐懼'],
            ['index' => 9, 'name' => '結果',     'description' => '最終可能的結局'],
        ],
    ],
];
