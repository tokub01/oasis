<?php

namespace App\Events\Network;

use App\Models\Post;

/** Persisted network change; consumed by the notification listeners. */
final class PostPublished
{
    /** Carry the saved models; dispatch only after successful publication/follow creation. */
    public function __construct(public readonly Post $post) {}
}
