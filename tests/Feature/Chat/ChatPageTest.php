<?php

use App\Models\User;

/* The chat page shell: access rules, navigation and the markup hooks resources/js/chat/app.js relies on. */

it('requires a signed-in, verified member', function () {
    $this->get('/app/nachrichten')->assertRedirect('/anmelden');
    $this->actingAs(User::factory()->unverified()->create())->get('/app/nachrichten')->assertRedirect();
});

it('renders every chat screen, the report dialog and the no-JavaScript notice', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/app/nachrichten')->assertOk();

    $response->assertSee('data-oasis-chat', false)
        ->assertSee('data-user-id="'.$user->id.'"', false)
        ->assertSee('data-api="'.url('/app/chat/api').'"', false)
        ->assertSee('Dafür braucht diese Seite JavaScript.');
    foreach (['loading', 'setup', 'unlock', 'list', 'new', 'conversation', 'details'] as $screen) {
        $response->assertSee('data-screen="'.$screen.'"', false);
    }
    $response->assertSee('data-report-dialog', false);
});

it('links "Nachrichten" in the app navigation and marks it as current on the chat page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/app')->assertSee('href="'.route('app.chat').'"', false);
    $html = $this->actingAs($user)->get('/app/nachrichten')->getContent();
    expect($html)->toMatch('#href="'.preg_quote(route('app.chat'), '#').'"\s+aria-current="page"#');
});

it('tells the chat UI whether the account may write', function () {
    $this->actingAs(User::factory()->create())->get('/app/nachrichten')->assertSee('data-can-participate="1"', false);
    $this->actingAs(User::factory()->create(['trial_ends_at' => now()->subDay()]))->get('/app/nachrichten')
        ->assertSee('data-can-participate="0"', false);
});
