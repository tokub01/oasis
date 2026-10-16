<?php

namespace Tests\Support;

use App\Livewire\Network\Compose;
use App\Livewire\Network\Discover;
use App\Livewire\Network\Invitations;
use App\Livewire\Network\OasisClose;
use App\Livewire\Network\OasisForm;
use App\Livewire\Network\OasisShow;
use App\Livewire\Network\ProfileEdit;
use App\Livewire\Network\ProfileShow;
use App\Models\Oasis;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** Readable migration fixtures: execute real Livewire endpoints with server-bound contexts instead of retired HTTP posts. */
class NetworkActions
{
    /** Publish through the real composer, keeping route context immutable and testing mount authorization too.
     * @param  array<string, mixed>  $input
     */
    public static function publish(array $input, ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $params = ['replyToId' => $input['reply_to'] ?? null, 'carriedId' => $input['carried'] ?? null,
            'oasisId' => isset($input['oasis']) ? Oasis::where('slug', $input['oasis'])->value('id') : null];
        unset($input['reply_to'], $input['carried'], $input['oasis']);
        if (isset($input['images'])) {
            $input['alts'] = array_map(fn ($index) => $input['alts'][$index] ?? '', array_keys($input['images']));
            $input['images'] = array_values($input['images']);
        }
        $component = Livewire::test(Compose::class, $params);

        return $component->instance() ? $component->set($input)->call('publish') : $component;
    }

    /** A post-card endpoint is shared by every list and independently checks its target policies. */
    public static function post(string $method, int $postId, ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(Discover::class);

        return $component->instance() ? $component->call($method, $postId) : $component;
    }

    /** Profile actions always bind the visible handle first. */
    public static function profile(string $method, User $target, ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(ProfileShow::class, ['handle' => $target->username]);

        return $component->instance() ? $component->call($method) : $component;
    }

    /** Oasis membership actions use the same component as the visible oasis page. */
    public static function oasis(string $method, Oasis $oasis, ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(OasisShow::class, ['oasis' => $oasis]);

        return $component->instance() ? $component->call($method) : $component;
    }

    /** Create/edit through validated editable fields; context IDs are never assigned via set().
     * @param  array<string, mixed>  $input
     */
    public static function saveOasis(array $input, ?Oasis $oasis = null, ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(OasisForm::class, $oasis ? ['oasis' => $oasis] : []);

        return $component->instance() ? $component->set($input)->call('save') : $component;
    }

    /** Roles are checked again by OasisForm and ManageOasis. */
    public static function role(Oasis $oasis, User $user, string $role, ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(OasisForm::class, ['oasis' => $oasis]);

        return $component->instance() ? $component->call('setRole', $user->id, $role) : $component;
    }

    /** Closing needs a current host and explicit checkbox consent.
     * @param  array<string, mixed>  $input
     */
    public static function close(Oasis $oasis, array $input = [], ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(OasisClose::class, ['oasis' => $oasis]);

        return $component->instance() ? $component->set($input)->call('close') : $component;
    }

    /** Save only the current member's editable profile.
     * @param  array<string, mixed>  $input
     */
    public static function saveProfile(array $input, ?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(ProfileEdit::class);

        return $component->instance() ? $component->set($input)->call('save') : $component;
    }

    /** Generate an own invitation using the existing monthly domain budget. */
    public static function invite(?User $actor = null): Testable
    {
        if ($actor) {
            Livewire::actingAs($actor);
        }
        $component = Livewire::test(Invitations::class);

        return $component->instance() ? $component->call('create') : $component;
    }
}
