<?php

// 偉特塔羅 78 張牌資料
// 大阿爾克那 22 張（arcana: major, suit: null, number: 0-21）
// 小阿爾克那 56 張（arcana: minor, suit: wands/cups/swords/pentacles, number: 1-14）
// image_path 命名規則：
//   大阿爾克那 → major-00-fool.jpg, major-01-magician.jpg ...
//   小阿爾克那 → minor-wands-01.jpg, minor-cups-02.jpg ...

return array_merge(
    require __DIR__ . '/tarot_cards_major.php',
    require __DIR__ . '/tarot_cards_wands.php',
    require __DIR__ . '/tarot_cards_cups.php',
    require __DIR__ . '/tarot_cards_swords.php',
    require __DIR__ . '/tarot_cards_pentacles.php',
);
