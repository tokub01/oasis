<?php

use App\Actions\Network\ManageFollows;
use App\Livewire\Membership\Checkout;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\Support\NetworkActions;
use Tests\Support\NetworkFixtures as Network;

beforeEach(fn () => $this->withoutVite());

it('shows the actual checkout explanation after the guest registration redirect', function () {
    config()->set('oasis.checkout_enabled', true);
    $status = 'Erstelle bitte zuerst dein Oasis-Konto, um deine Mitgliedschaft abzuschließen.';
    Livewire::test(Checkout::class)
        ->set('stufe', 'oase')
        ->set('rhythmus', 'monat')
        ->call('checkout')
        ->assertRedirect(route('register'))
        ->assertSessionHas('status', $status);
    $this->get(route('register'))->assertOk()->assertSee($status);
});

it('records the correct delete and block forms and their direct effects', function () {
    $author = Network::member();
    $viewer = Network::member();
    $post = Network::post($author);
    foreach ([[$author, 'deletePost('.$post->id.')', 'Löschen'],
        [$viewer, 'blockUser('.$author->id.')', 'Blockieren']] as [$actor, $action, $label]) {
        $response = $this->actingAs($actor)->get(route('network.posts.show', $post))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($document);
        $forms = $xpath->query('//form[@*[name()="wire:submit" and .="'.$action.'"]]');
        expect($forms->length)->toBe(1);
        $form = $forms->item(0);
        expect($form->getAttribute('wire:submit'))->toBe($action);
        expect($xpath->query('.//button/@*[name()="wire:loading.attr"]', $form)->length)->toBe(1);
        expect($xpath->query('.//button', $form)->item(0)->textContent)->toContain($label);
    }
    app(ManageFollows::class)->follow($author, $viewer);
    app(ManageFollows::class)->follow($viewer, $author);
    NetworkActions::post('blockUser', $author->id, $viewer)->assertHasNoErrors();
    expect($author->fresh()->followers()->count())->toBe(0);
    expect($viewer->fresh()->followers()->count())->toBe(0);
    NetworkActions::post('deletePost', $post->id, $author->fresh())->assertHasNoErrors();
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

it('renders German notification pagination labels and navigation name', function () {
    $paginator = new LengthAwarePaginator(range(1, 20), 60, 20, 1, ['path' => '/app/hinweise']);
    $html = $paginator->links('pagination::tailwind')->toHtml();
    expect($html)->not->toContain('Pagination Navigation', 'Showing', 'pagination.next', 'pagination.previous');
});
