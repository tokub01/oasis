<?php

/*
 * Respektloser Umgangston (App\Support\Moderation\ToneHint and the chat client): words that earn a short, friendly
 * hint with a link to the rules – never a report, hold or strike. Lowercase, umlauts written out.
 * - always:  slang addresses that are disrespectful in practically every use;
 * - address: ordinary words that are only meant here as an address ("Alter, was soll das?"), so they count only at the
 *            start of a sentence or set off by a comma, never inside a normal sentence ("im Alter von 30").
 * Extend with care: false positives annoy people.
 */
return [
    'always' => ['digga', 'diggah', 'digger', 'brudi', 'brudda', 'bruda', 'alta', 'bratan', 'wallah', 'vallah'],
    'address' => ['alter', 'alte', 'bro', 'bruder'],
];
