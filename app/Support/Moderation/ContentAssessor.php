<?php

namespace App\Support\Moderation;

use App\Ai\Agents\ContentCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single entry point of the automated content check. Asks the language model (ContentCheck agent on Mistral, EU)
 * and falls back to the word list when the model fails or the check is switched off. Verdicts are cached by text hash
 * for a few minutes, so the "Nochmal lesen?" pre-check and the check after publishing cost one request.
 *
 * Nothing but the text leaves the server; errors are logged without the text.
 */
class ContentAssessor
{
    public function __construct(private readonly WordListCheck $wordList) {}

    /**
     * @param  bool  $strict  true in the background job: a model failure is thrown so the job retries;
     *                        false before publishing: never block posting, use the word list instead
     */
    public function assess(string $text, bool $strict = false): ContentVerdict
    {
        $text = trim($text);
        if ($text === '') {
            return ContentVerdict::clean('ai');
        }
        if (! config('oasis.moderation.enabled')) {
            return $this->wordList->verdict($text);
        }

        $key = 'content-check:'.hash('sha256', $text);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return ContentVerdict::fromArray($cached['scores'] + ['reason' => $cached['reason']], 'ai');
        }

        try {
            $response = (new ContentCheck)->prompt(
                '<text>'.$text.'</text>',
                provider: config('oasis.moderation.provider'),
                model: config('oasis.moderation.model'),
            );
            $verdict = ContentVerdict::fromArray($response->toArray(), 'ai');
            Cache::put($key, $verdict->toArray(), now()->addMinutes(15));

            return $verdict;
        } catch (Throwable $failure) {
            Log::warning('Automated content check failed', ['exception' => $failure::class]);
            if ($strict) {
                throw $failure;
            }

            return $this->wordList->verdict($text);
        }
    }
}
