<?php

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Tests\Support\NetworkFixtures as Network;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Calculates the relative luminance of an sRGB hex color according to WCAG 2.1.
 */
function relativeLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $toLinear = fn (float $c) => $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);

    return 0.2126 * $toLinear($r) + 0.7152 * $toLinear($g) + 0.0722 * $toLinear($b);
}

/**
 * Calculates WCAG 2.1 contrast ratio between two hex colors.
 */
function contrastRatio(string $hex1, string $hex2): float
{
    $l1 = relativeLuminance($hex1);
    $l2 = relativeLuminance($hex2);
    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);

    return ($lighter + 0.05) / ($darker + 0.05);
}

it('FINDING B1: focus-visible indicator meets WCAG 2.1 SC 1.4.11 non-text contrast across light and dark surfaces', function () {
    // WCAG 2.1 SC 1.4.11 requires >= 3.0:1 contrast for user interface focus states.
    $css = File::get(resource_path('css/oasis.css'));

    // Extract color tokens from oasis.css
    preg_match('/--color-sand:\s*(#[0-9a-fA-F]{6});/', $css, $sandMatch);
    preg_match('/--color-spring-text:\s*(#[0-9a-fA-F]{6});/', $css, $springTextMatch);
    preg_match('/--color-spring:\s*(#[0-9a-fA-F]{6});/', $css, $springMatch);
    preg_match('/--color-night:\s*(#[0-9a-fA-F]{6});/', $css, $nightMatch);

    $sand = $sandMatch[1] ?? '#F4EDE1';
    $springText = $springTextMatch[1] ?? '#1F6F69';
    $spring = $springMatch[1] ?? '#2E9E95';
    $night = $nightMatch[1] ?? '#0E2B2F';

    // 1. Default focus outline in oasis.css uses --color-spring-text on light backgrounds:
    expect($css)->toMatch('/:focus-visible\s*\{\s*outline:[^;]*var\(--color-spring-text\)/');
    $lightRatio = contrastRatio($springText, $sand);
    expect($lightRatio)->toBeGreaterThanOrEqual(3.0); // ~5.10:1

    // 2. Dark surfaces use --color-spring outline on dark backgrounds:
    expect($css)->toMatch('/\.bg-night[^;]*:focus-visible[^{]*\{\s*outline-color:\s*var\(--color-spring\)/');
    $darkRatio = contrastRatio($spring, $night);
    expect($darkRatio)->toBeGreaterThanOrEqual(3.0); // ~4.58:1

    // 3. Field component uses spring-text outline:
    $fieldBlade = File::get(resource_path('views/components/oasis/field.blade.php'));
    expect($fieldBlade)->toContain('focus-within:outline-spring-text');
});

it('FINDING S1: text on dune background meets WCAG 2.1 SC 1.4.3 minimum contrast for normal text', function () {
    // WCAG 2.1 SC 1.4.3 requires >= 4.5:1 for regular text.
    // In membership/index.blade.php, gift cards on bg-dune must use text-ink-2 rather than text-muted.
    $membershipBlade = File::get(resource_path('views/app/membership/index.blade.php'));
    expect($membershipBlade)->toMatch('/bg-dune[\s\S]*?text-ink-2[\s\S]*?12 Monate für/');

    $css = File::get(resource_path('css/oasis.css'));
    preg_match('/--color-dune:\s*(#[0-9a-fA-F]{6});/', $css, $duneMatch);
    preg_match('/--color-ink-2:\s*(#[0-9a-fA-F]{6});/', $css, $ink2Match);

    $dune = $duneMatch[1] ?? '#EADFCC';
    $ink2 = $ink2Match[1] ?? '#3E4A47';

    $ratio = contrastRatio($ink2, $dune);
    expect($ratio)->toBeGreaterThanOrEqual(4.5); // ~7.00:1
});

it('FINDING S2: chat loading screen is hidden when JavaScript is disabled', function () {
    $user = Network::member();

    $response = $this->actingAs($user)->get(route('app.chat'));
    $response->assertOk();

    $html = $response->getContent();

    // The page shows noscript warning explaining that JavaScript is needed.
    expect($html)->toContain('Nachrichten werden in deinem Browser verschlüsselt');

    // Without JS, the loading section must be hidden, either via hidden attribute or via noscript CSS rule.
    $hasHiddenAttr = (bool) preg_match('/<section[^>]*data-screen=["\']loading["\'][^>]*\bhidden\b/', $html)
        || (bool) preg_match('/<section[^>]*\bhidden\b[^>]*data-screen=["\']loading["\']/', $html);
    $hasNoscriptHide = (bool) preg_match('/<noscript>[\s\S]*?\[data-screen=["\']loading["\']\]\s*\{\s*display:\s*none/i', $html);

    expect($hasHiddenAttr || $hasNoscriptHide)->toBeTrue();
});

it('FINDING S3: registration page renders session status flash message with role status', function () {
    $statusText = 'Erstelle bitte zuerst dein Oasis-Konto, um deine Mitgliedschaft abzuschließen.';

    $response = $this->withSession(['status' => $statusText])->get(route('register'));
    $response->assertOk();

    $response->assertSee($statusText);
    $response->assertSee('role="status"', false);
});

it('FINDING S4: post deletion and author blocking provide confirmation safeguards without inline JS', function () {
    $author = Network::member();
    $viewer = Network::member();
    $post = Network::post($author, ['body' => 'Ein wichtiger Tropfen']);

    // Author sees two-step confirmation details element for deletion
    $authorResponse = $this->actingAs($author)->get(route('network.posts.show', $post));
    $authorResponse->assertOk();
    $authorHtml = $authorResponse->getContent();

    // Must not use CSP-violating inline handlers
    expect($authorHtml)->not->toMatch('/action=["\'][^"\']*\/tropfen\/'.$post->id.'["\'][^>]*onclick=/i');
    expect($authorHtml)->toMatch('/<details[^>]*>[\s\S]*?Wirklich löschen\?[\s\S]*?action=["\'][^"\']*\/tropfen\/'.$post->id.'["\'][\s\S]*?Löschen bestätigen/i');

    // Viewer sees two-step confirmation details element for blocking
    $viewerResponse = $this->actingAs($viewer)->get(route('network.posts.show', $post));
    $viewerResponse->assertOk();
    $viewerHtml = $viewerResponse->getContent();

    expect($viewerHtml)->not->toMatch('/action=["\'][^"\']*\/sicherheit\/block\/'.$author->id.'["\'][^>]*onclick=/i');
    expect($viewerHtml)->toMatch('/<details[^>]*>[\s\S]*?Wirklich blockieren\?[\s\S]*?action=["\'][^"\']*\/sicherheit\/block\/'.$author->id.'["\'][\s\S]*?Blockieren bestätigen/i');
});

it('FINDING S5: invitation links provide progressive enhancement copy action', function () {
    $member = Network::member();
    Invitation::create([
        'inviter_id' => $member->id,
        'token' => 'test-invitation-token-1234',
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($member)->get(route('network.invitations.index'));
    $response->assertOk();

    // Verify copy button with data-oasis="share" exists alongside the readonly input
    $response->assertSee('data-oasis="share"', false);
    $response->assertSee('Link kopieren');
});

it('FINDING N1: oasis theme defines valid theme color tokens for all oasis colors', function () {
    $css = File::get(resource_path('css/oasis.css'));

    // Verify all four oasis colors are present in design tokens
    expect($css)->toContain('--color-sun:');
    expect($css)->toContain('--color-spring:');
    expect($css)->toContain('--color-night:');
    expect($css)->toContain('--color-dune:');
});

it('FINDING N2: public manifest page correctly populates configured founder content', function () {
    config()->set('oasis.founder.name', 'Oasis Gründerteam');
    config()->set('oasis.founder.photo', '/images/team.jpg');
    config()->set('oasis.founder.text', 'Unsere Vision für ein ruhiges Netz.');

    $response = $this->get(route('oasis.manifest'));
    $response->assertOk();

    $response->assertSee('Oasis Gründerteam');
    $response->assertDontSee('[DEIN NAME]');
    $response->assertDontSee('[FOTO]');
});

it('FINDING S6 & N4: pagination uses Oasis design tokens and German accessibility labels', function () {
    $paginator = new LengthAwarePaginator(range(1, 20), 60, 20, 1, ['path' => '/app/hinweise']);
    $html = $paginator->links('pagination::tailwind')->toHtml();

    expect($html)
        ->toContain('Seitennavigation')
        ->toContain('Zeige')
        ->toContain('Ergebnisse')
        ->not->toContain('Pagination Navigation', 'Showing', 'pagination.next', 'pagination.previous')
        ->not->toContain('gray-', 'border-blue-');
});
