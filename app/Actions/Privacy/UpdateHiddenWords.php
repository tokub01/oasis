<?php

namespace App\Actions\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds and removes a member's hidden words ("Verborgene Wörter"). A word hides Tropfen and replies containing it from
 * the member's own lists, suppresses hints with it, and hides replies with it under the member's Tropfen from everyone
 * but their writer. Stored lower case, without duplicates, at most MAX words.
 */
class UpdateHiddenWords
{
    public const MAX = 50;

    /** @throws ValidationException for an empty, too long or duplicate word, or when the list is full */
    public function add(User $member, string $word): void
    {
        $word = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $word) ?? ''));
        Validator::make(['word' => $word], ['word' => ['required', 'string', 'min:2', 'max:40']], [
            'word.required' => 'Gib ein Wort ein.',
            'word.min' => 'Ein Wort braucht mindestens 2 Zeichen.',
            'word.max' => 'Ein Wort darf höchstens 40 Zeichen haben.',
        ])->validate();

        $words = $member->privacy()->hiddenWords();
        if (in_array($word, $words, true)) {
            throw ValidationException::withMessages(['word' => 'Dieses Wort steht schon auf deiner Liste.']);
        }
        if (count($words) >= self::MAX) {
            throw ValidationException::withMessages(['word' => 'Du kannst höchstens '.self::MAX.' Wörter verbergen.']);
        }
        $this->save($member, [...$words, $word]);
    }

    public function remove(User $member, string $word): void
    {
        $this->save($member, array_values(array_diff($member->privacy()->hiddenWords(), [$word])));
    }

    /** @param  list<string>  $words */
    private function save(User $member, array $words): void
    {
        $settings = $member->privacySetting()->updateOrCreate([], ['hidden_words' => $words === [] ? null : $words]);
        $member->setRelation('privacySetting', $settings);
    }
}
