<?php

namespace Tests\Support;

use App\Actions\Network\ManageOasis;
use App\Models\Oasis;
use App\Models\Post;
use App\Models\User;

/** Builders for the stage 2 feature tests (tests/Feature/Network). */
final class NetworkFixtures
{
    /** A verified member in an active trial. */
    public static function member(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /** A verified member whose trial ended without a membership: read-only. */
    public static function readOnlyMember(): User
    {
        return User::factory()->create(['trial_ends_at' => now()->subDay()]);
    }

    /** A Tropfen written directly (bypasses the HTTP layer). */
    public static function post(User $author, array $attributes = []): Post
    {
        $post = (new Post)->forceFill($attributes + [
            'user_id' => $author->id, 'body' => 'Ein Tropfen von '.$author->username, 'visibility' => 'public',
        ]);
        $post->save();

        return $post;
    }

    /** An oasis founded by the given member (first host and member). */
    public static function oasis(User $host, array $attributes = []): Oasis
    {
        return app(ManageOasis::class)->create($host, $attributes + [
            'name' => 'Korn & Licht', 'description' => 'Analoge Fotografie.', 'house_rules' => 'Freundlich bleiben.',
            'visibility' => 'public', 'color' => 'sun',
        ]);
    }
}
