<?php

namespace App\Actions\Moderation;

use App\Enums\Moderation\Outcome;
use App\Models\ContentCheck;
use App\Models\ModerationDecision;
use App\Models\Post;

/**
 * After a human decision on an automated detection: "no action" makes a held Tropfen visible again. Any other outcome
 * keeps it hidden (the decision itself describes the restriction to the author).
 */
class ReleaseHeldContent
{
    public function handle(ModerationDecision $decision): void
    {
        $source = $decision->case->source;
        if (! $source instanceof ContentCheck || $decision->outcome !== Outcome::NoAction) {
            return;
        }
        $post = $source->checkable;
        if ($post instanceof Post && $post->held_at !== null) {
            $post->forceFill(['held_at' => null])->save();
        }
    }
}
