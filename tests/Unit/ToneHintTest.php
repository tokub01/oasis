<?php

/* Umgangston hint: slang addresses are found, ordinary use of the same words is not. */

use App\Support\Moderation\ToneHint;
use Tests\TestCase;

uses(TestCase::class);

it('finds slang addresses', function (string $text, string $word) {
    expect(ToneHint::find($text))->toBe($word);
})->with([
    ['Digga was geht', 'Digga'],
    ['ey brudi, komm mal klar', 'brudi'],
    ['Alter, was soll das?', 'Alter'],
    ['Hör auf damit, alte!', 'alte'],
    ['Das ist doch Quatsch, bro', 'bro'],
    ['Wallah das stimmt', 'Wallah'],
]);

it('leaves ordinary sentences alone', function (string $text) {
    expect(ToneHint::find($text))->toBeNull();
})->with([
    'Im Alter von 30 habe ich angefangen',
    'Die alte Brücke ist wunderschön',
    'Mein Bruder kommt morgen',
    'Mann, ist das ein schöner Tag!',
    'Der Digital-Workshop war gut',
]);
