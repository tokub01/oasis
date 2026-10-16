<?php

namespace App\Livewire\Network;

use App\Actions\Network\PublishPost;
use App\Models\Oasis;
use App\Models\Post;
use App\Support\Moderation\ContentAssessor;
use App\Support\Moderation\WordListCheck;
use App\Support\Network\PostInput;
use App\Support\Network\TextLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Reactive Tropfen/reply/carry composer; input is validated and context policies are repeated on every write. */
class Compose extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $oasisId = null;

    #[Locked]
    public ?int $replyToId = null;

    #[Locked]
    public ?int $carriedId = null;

    #[Locked]
    public bool $showImages = true;

    public string $body = '';

    public string $visibility = 'public';

    public string $kind = 'post';

    public mixed $link_read = false;

    public bool $tone_confirmed = false;

    #[Locked]
    public bool $toneNudge = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $images = [];

    /** @var array<int, string> */
    public array $alts = [];

    #[Locked]
    public string $status = '';

    /** Bind server-provided context identifiers without serializing context models in the HTML snapshot. */
    public function mount(?int $oasisId = null, ?int $replyToId = null, ?int $carriedId = null, bool $showImages = true): void
    {
        $this->showImages = $showImages;
        $this->oasisId = $oasisId;
        $this->replyToId = $replyToId;
        $this->carriedId = $carriedId;
        $this->authorizeContexts();
        $this->visibility = auth()->user()->profileOrDefault()->visibility->value;
    }

    /** Context records are always loaded fresh, so blocks, removals and lost oasis roles apply immediately.
     * @return array{oasis: Oasis|null, reply: Post|null, carried: Post|null}
     */
    private function authorizeContexts(): array
    {
        abort_unless(auth()->user()?->hasVerifiedEmail(), 403);
        Gate::authorize('create', Post::class);
        $oasis = $this->oasisId ? Oasis::findOrFail($this->oasisId) : null;
        $reply = $this->replyToId ? Post::findOrFail($this->replyToId) : null;
        $carried = $this->carriedId ? Post::findOrFail($this->carriedId) : null;
        abort_if($reply && $carried, 422);
        foreach ([$reply, $carried] as $post) {
            if ($post) {
                Gate::authorize('interact', $post);
            }
        }
        $oasis ??= $reply?->oasis;
        if ($oasis) {
            Gate::authorize('post', $oasis);
        }

        return compact('oasis', 'reply', 'carried');
    }

    /** Check drafts locally; unpublished text never reaches the language model during typing. */
    public function updatedBody(): void
    {
        $this->authorizeContexts();
        $this->tone_confirmed = false;
        $this->toneNudge = false;
        if (trim($this->body) !== '' && mb_strlen($this->body) <= Post::MAX_LENGTH) {
            RateLimiter::attempt('network-tone:'.auth()->id(), 30, function (): void {
                $this->toneNudge = app(WordListCheck::class)->verdict($this->body)->shouldNudge();
            });
        }
    }

    /** Reject invalid selected images immediately, after rechecking the current posting context. */
    public function updatedImages(): void
    {
        $this->authorizeContexts();
        $this->resetValidation(['images', 'images.*']);
        $rules = PostInput::rules();
        $this->validate(['images' => $rules['images'], 'images.*' => $rules['images.*']], PostInput::messages());
    }

    /** Remove an own temporary image and keep the remaining descriptions aligned with their files. */
    public function removeImage(int $index): void
    {
        $this->authorizeContexts();
        unset($this->images[$index], $this->alts[$index]);
        $this->alts = array_map(fn ($key) => $this->alts[$key] ?? '', array_keys($this->images));
        $this->images = array_values($this->images);
        $this->resetValidation(['alts', 'alts.*']);
        $this->updatedImages();
    }

    /** Validate then assess submitted text; valid attempts, including tone confirmations, count toward the budget. */
    public function publish(): void
    {
        $context = $this->authorizeContexts();
        $rules = PostInput::rules($context['carried']);
        $this->validate($rules, PostInput::messages());
        foreach ($this->images as $index => $file) {
            if (trim($this->alts[$index] ?? '') === '') {
                $this->addError('alts.'.$index, 'Beschreib das Bild kurz für Menschen, die es nicht sehen.');

                return;
            }
        }
        if (RateLimiter::tooManyAttempts('network-posts:'.auth()->id(), 10)) {
            $this->addError('body', 'Du hast gerade viele Tropfen geteilt. Warte bitte eine Minute.');

            return;
        }
        RateLimiter::hit('network-posts:'.auth()->id(), 60);
        if (! $this->tone_confirmed && app(ContentAssessor::class)->assess($this->body)->shouldNudge()) {
            $this->toneNudge = true;
            $this->addError('body', 'Nochmal lesen? Dein Tropfen klingt verletzend. Vielleicht findest du Worte, die dein Gegenüber besser hören kann. Wenn du es genau so meinst, bestätige unten „So senden“.');

            return;
        }
        $images = [];
        foreach ($this->images as $index => $file) {
            $images[] = ['file' => $file, 'alt' => trim($this->alts[$index])];
        }
        $post = app(PublishPost::class)->handle(auth()->user(), ['body' => $this->body, 'visibility' => $this->visibility,
            'kind' => $this->kind, 'oasis' => $context['oasis'], 'reply_to' => $context['reply'], 'carried' => $context['carried']], $images);
        $this->reset('body', 'images', 'alts', 'tone_confirmed', 'toneNudge', 'link_read');
        $this->status = $post->reply_to_id ? 'Deine Antwort ist da.' : 'Dein Tropfen ist da.';
        $this->dispatch('post-published');
        if ($context['carried']) {
            $this->redirectRoute('app.home', navigate: true);
        }
    }

    /** Even background refreshes may not keep a composer open after membership or target access is revoked. */
    public function render(): View
    {
        $context = $this->authorizeContexts();

        return view('livewire.network.compose', $context + ['links' => TextLinks::in($context['carried']->body ?? '')]);
    }
}
