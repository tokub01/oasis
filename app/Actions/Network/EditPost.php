<?php

namespace App\Actions\Network;

use App\Jobs\Moderation\CheckContent;
use App\Models\Post;
use App\Models\User;
use App\Support\Network\PostInput;
use App\Support\Security\AccountLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Changes the text of a Tropfen within Post::EDIT_MINUTES (docs/oasis-network.md, "Bearbeiten"). The earlier text is
 * kept as a revision, the new one is checked again (automated check, limits for new accounts). Images, audience and
 * oasis stay; new mentions send no hints, so editing cannot be used to ping people.
 */
class EditPost
{
    /** @throws ValidationException for an invalid text */
    public function handle(User $author, Post $post, string $body): Post
    {
        Gate::forUser($author)->authorize('update', $post);
        $body = Validator::make(['body' => $body], ['body' => PostInput::rules()['body']], PostInput::messages())->validate()['body'];
        if (trim($body) === trim($post->body)) {
            return $post;
        }
        AccountLimits::checkLinksAndMentions($author, $body);

        DB::transaction(function () use ($post, $body): void {
            $post->revisions()->create(['body' => $post->body, 'replaced_at' => now()]);
            $post->forceFill(['body' => $body, 'edited_at' => now()])->save();
        });
        CheckContent::for($post);

        return $post;
    }
}
