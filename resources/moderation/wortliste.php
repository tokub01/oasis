<?php

/*
 * Fallback word list for the automated content check (App\Support\Moderation\WordListCheck), used only when the
 * language model is unreachable. Deliberately short and conservative: whole words, lowercase, umlauts written out.
 * A hit is a hint for human moderators, never a decision. Extend with care – false positives create work.
 */
return [
    'insult' => [
        'arschloch', 'wichser', 'hurensohn', 'fotze', 'missgeburt', 'spast', 'spasti', 'mongo', 'vollidiot',
        'drecksau', 'schlampe',
    ],
    'hate' => [
        'kanake', 'kanaken', 'neger', 'zigeuner', 'schwuchtel', 'judensau', 'untermensch', 'untermenschen',
    ],
    'threat' => [
        'abstechen', 'umbringen', 'abknallen', 'kaltmachen', 'aufschlitzen',
    ],
    'sexual' => [
        'ficken', 'fick', 'porno', 'nacktbilder', 'nudes', 'wichsen', 'sperma',
    ],
];
