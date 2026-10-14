<?php

use App\Actions\Network\ManageFollows;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Support\NetworkFixtures as Network;

beforeEach(fn () => $this->withoutVite());

it('shows the actual checkout explanation after the guest registration redirect', function () {
    config()->set('oasis.checkout_enabled', true);
    $status = 'Erstelle bitte zuerst dein Oasis-Konto, um deine Mitgliedschaft abzuschließen.';
    $response = $this->post('/mitglied-werden/checkout', ['stufe' => 'oase', 'rhythmus' => 'monat']);
    $response->assertRedirect(route('register'))->assertSessionHas('status', $status);
    $this->get($response->headers->get('Location'))->assertOk()->assertSee($status);
});

it('records the correct delete and block forms and their direct effects', function () {
    $author = Network::member();
    $viewer = Network::member();
    $post = Network::post($author);
    foreach ([[$author, route('network.posts.destroy', $post), 'Löschen'],
        [$viewer, route('safety.block.store', $author), 'Blockieren']] as [$actor, $action, $label]) {
        $response = $this->actingAs($actor)->get(route('network.posts.show', $post))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($document);
        $forms = $xpath->query('//form[@action="'.$action.'"]');
        expect($forms->length)->toBe(1);
        $form = $forms->item(0);
        expect($form->getAttribute('method'))->toBe('POST');
        expect($xpath->query('.//button', $form)->item(0)->textContent)->toContain($label);
    }
    app(ManageFollows::class)->follow($author, $viewer);
    app(ManageFollows::class)->follow($viewer, $author);
    $this->actingAs($viewer)->post(route('safety.block.store', $author))->assertRedirect();
    expect($author->fresh()->followers()->count())->toBe(0);
    expect($viewer->fresh()->followers()->count())->toBe(0);
    $this->actingAs($author->fresh())->delete(route('network.posts.destroy', $post))->assertRedirect();
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

it('renders German notification pagination labels and navigation name', function () {
    $paginator = new LengthAwarePaginator(range(1, 20), 60, 20, 1, ['path' => '/app/hinweise']);
    $html = $paginator->links('pagination::tailwind')->toHtml();
    expect($html)->not->toContain('Pagination Navigation', 'Showing', 'pagination.next', 'pagination.previous');
});
