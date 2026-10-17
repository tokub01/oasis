<?php

namespace App\Livewire\Privacy;

use App\Actions\Privacy\UpdateHiddenWords;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Verborgene Wörter" on /app/privatsphaere/woerter (route app.privacy.words): the member adds and removes words; every
 * change applies at once (UpdateHiddenWords, enforced in PostFeed, Post::isVisibleTo() and SendHint).
 */
class HiddenWords extends Component
{
    public string $word = '';

    /** Confirmation of the last change, announced through role="status"; set by the server only. */
    #[Locked]
    public ?string $status = null;

    public function add(UpdateHiddenWords $words): void
    {
        try {
            $words->add(auth()->user(), $this->word);
        } catch (ValidationException $invalid) {
            $this->status = null;
            $this->addError('word', $invalid->validator->errors()->first());

            return;
        }
        $this->status = '„'.mb_strtolower(trim($this->word)).'“ wird jetzt verborgen.';
        $this->reset('word');
    }

    /** Remove one word, addressed by the md5 key the list shows (keeps arbitrary text out of the click expression). */
    public function remove(string $key, UpdateHiddenWords $words): void
    {
        $word = collect(auth()->user()->privacy()->hiddenWords())->first(fn (string $hidden) => md5($hidden) === $key);
        abort_if($word === null, 404);
        $words->remove(auth()->user(), $word);
        $this->status = '„'.$word.'“ wird nicht mehr verborgen.';
    }

    public function render(): View
    {
        return view('livewire.privacy.hidden-words', [
            'words' => auth()->user()->privacy()->hiddenWords(),
            'max' => UpdateHiddenWords::MAX,
        ])->layout('layouts.app', ['title' => 'Verborgene Wörter', 'active' => 'privatsphaere', 'wide' => true]);
    }
}
