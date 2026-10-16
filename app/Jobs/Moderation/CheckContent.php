<?php

namespace App\Jobs\Moderation;

use App\Actions\Moderation\ApplyContentVerdict;
use App\Models\ContentCheck;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\Profile;
use App\Support\Moderation\ContentAssessor;
use App\Support\Moderation\WordListCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Background check of one published text. Retries with growing pauses when the language model is unreachable;
 * after the last attempt the word list decides (`failed`), so every text is checked one way or the other.
 * The scheduled `oasis:moderation-sweep` re-dispatches texts whose check never ran (e.g. queue down).
 */
class CheckContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> seconds between attempts */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly ContentCheck $check) {}

    /** Register a (new or changed) text for checking – one row per item – and queue the check. */
    public static function for(Post|Profile|Oasis $checkable): void
    {
        $check = ContentCheck::firstOrCreate([
            'checkable_type' => $checkable->getMorphClass(),
            'checkable_id' => $checkable->getKey(),
        ]);
        $check->forceFill(['status' => 'pending', 'attempts' => 0])->save();
        try {
            self::dispatch($check);
        } catch (Throwable $failure) {
            // Only reachable with a synchronous queue: publishing must never fail because the check failed.
            // The check stays pending; oasis:moderation-sweep retries it.
            Log::warning('Automated content check could not run now', ['exception' => $failure::class]);
        }
    }

    public function handle(ContentAssessor $assessor, ApplyContentVerdict $apply): void
    {
        $text = $this->text($this->check->checkable);
        if ($text === null) {
            $this->check->delete();

            return;
        }
        $this->check->increment('attempts');
        $apply->handle($this->check, $assessor->assess($text, strict: true));
    }

    /** Last resort after all retries: the word list still flags obvious cases for a human. */
    public function failed(?Throwable $exception): void
    {
        $text = $this->text($this->check->fresh()?->checkable);
        if ($text !== null) {
            app(ApplyContentVerdict::class)->handle($this->check, app(WordListCheck::class)->verdict($text));
        }
    }

    /** The public text of the item, or null if it no longer exists. */
    private function text(?Model $checkable): ?string
    {
        return match (true) {
            $checkable instanceof Post => $checkable->body,
            $checkable instanceof Profile => trim($checkable->bio.' '.$checkable->location),
            $checkable instanceof Oasis => trim($checkable->name."\n".$checkable->description."\n".$checkable->house_rules),
            default => null,
        };
    }
}
