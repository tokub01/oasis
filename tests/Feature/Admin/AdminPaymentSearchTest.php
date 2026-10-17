<?php

/* Admin payment search (/admin/zahlungen): local records, also of deleted accounts, and read-only lookups at Mollie. */

use App\Actions\Account\DeleteAccount;
use App\Enums\Role;
use App\Livewire\Admin\Payments;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\GetCustomerRequest;
use Mollie\Api\Http\Requests\GetPaginatedCustomerPaymentsRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Laravel\Facades\Mollie;
use Tests\Support\MollieFixtures;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

function paymentAdmin(): User
{
    return User::factory()->create(['role' => Role::Admin, 'two_factor_secret' => encrypt('second-factor'), 'two_factor_confirmed_at' => now()]);
}

/** Mollie's payment resource with its own dashboard link. */
function molliePayment(string $id, array $attributes = []): array
{
    return $attributes + ['resource' => 'payment', 'id' => $id, 'status' => 'paid', 'method' => 'creditcard',
        'amount' => ['currency' => 'EUR', 'value' => '5.00'], 'description' => 'Oasis Oase', 'customerId' => 'cst_test',
        'createdAt' => '2026-10-01T10:00:00+00:00', 'paidAt' => '2026-10-01T10:01:00+00:00',
        '_links' => ['dashboard' => ['href' => 'https://my.mollie.com/dashboard/org_1/payments/'.$id, 'type' => 'text/html']]];
}

describe('admin payment search', function () {
    it('is open to admins with 2FA only', function () {
        $this->actingAs(N::member())->get(route('admin.payments'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Moderator, 'two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()]))
            ->get(route('admin.payments'))->assertForbidden();
        $this->actingAs(paymentAdmin())->get(route('admin.payments'))->assertOk()->assertSeeLivewire(Payments::class)->assertSee('Zahlungen');
    });

    it('finds records by e-mail, name, number and Mollie id, also of deleted accounts', function () {
        $deleted = N::member(['display_name' => 'Greta Gelöscht', 'email' => 'greta@example.tld']);
        $kept = N::member(['display_name' => 'Karl Konto']);
        $old = MollieFixtures::paidRecord($deleted);
        $current = MollieFixtures::paidRecord($kept);
        app(DeleteAccount::class)->handle($deleted);

        $page = Livewire::actingAs(paymentAdmin())->test(Payments::class);
        $page->set('q', 'greta@example')->assertSee('#'.$old->id)->assertSee('Konto gelöscht – Beleg gesperrt')->assertDontSee('Karl Konto');
        $page->set('q', 'Karl')->assertSee('#'.$current->id)->assertDontSee('Greta Gelöscht');
        $page->set('q', '#'.$old->id)->assertSee('Greta Gelöscht')->assertDontSee('Karl Konto');
        $page->set('q', $current->mollie_payment_id)->assertSee('Karl Konto')->assertDontSee('Greta Gelöscht')->assertSee('Direkt bei Mollie suchen');
        $page->set('q', 'cst_member'.$deleted->id)->assertSee('Greta Gelöscht');
        $page->set('q', '')->set('status', 'erstattet')->assertSee('Keine Belege gefunden.');
    });

    it('checks a payment live at Mollie, shows Mollie’s dashboard link and logs the lookup without names', function () {
        Log::spy();
        $record = MollieFixtures::paidRecord(N::member());
        Mollie::fake([GetPaymentRequest::class => MockResponse::ok(molliePayment($record->mollie_payment_id, ['amountRefunded' => ['currency' => 'EUR', 'value' => '5.00']]))]);
        $admin = paymentAdmin();

        Livewire::actingAs($admin)->test(Payments::class)->call('checkPayment', $record->id)
            ->assertSee('Live bei Mollie')->assertSee('Kreditkarte')->assertSee('erstattet 5,00')
            ->assertSeeHtml('href="https://my.mollie.com/dashboard/org_1/payments/'.$record->mollie_payment_id.'"');

        Log::shouldHaveReceived('info')->with('Admin Mollie lookup', ['admin_id' => $admin->id, 'kind' => 'payment', 'mollie_id' => $record->mollie_payment_id]);
    });

    it('never links anything but Mollie and reports unknown ids in German', function () {
        $record = MollieFixtures::paidRecord(N::member());
        Mollie::fake([GetPaymentRequest::class => MockResponse::ok(molliePayment($record->mollie_payment_id,
            ['_links' => ['dashboard' => ['href' => 'https://evil.tld/phish', 'type' => 'text/html']]]))]);
        Livewire::actingAs(paymentAdmin())->test(Payments::class)->call('checkPayment', $record->id)
            ->assertSee('Live bei Mollie')->assertDontSee('evil.tld')->assertDontSee('Im Mollie-Dashboard öffnen');

        Mollie::fake([GetPaymentRequest::class => MockResponse::notFound('No payment exists with token tr_unknown1.')]);
        Livewire::actingAs(paymentAdmin())->test(Payments::class)->set('q', 'tr_unknown1')->call('searchMollie')
            ->assertSee('Bei Mollie gibt es keine Zahlung mit der ID tr_unknown1.');
        Livewire::actingAs(paymentAdmin())->test(Payments::class)->set('q', 'Greta')->call('searchMollie')
            ->assertSee('Gib eine Mollie-ID ein');
    });

    it('searches a customer directly at Mollie with all their payments, even without a local record', function () {
        Mollie::fake([
            GetCustomerRequest::class => MockResponse::ok(['resource' => 'customer', 'id' => 'cst_only1', 'name' => 'Nur Bei Mollie',
                'email' => 'mollie@example.tld', 'createdAt' => '2026-09-01T10:00:00+00:00',
                '_links' => ['dashboard' => ['href' => 'https://my.mollie.com/dashboard/org_1/customers/cst_only1', 'type' => 'text/html']]]),
            GetPaginatedCustomerPaymentsRequest::class => MockResponse::ok(['count' => 2, '_embedded' => ['payments' => [
                molliePayment('tr_first1'), molliePayment('tr_second2', ['status' => 'failed', 'paidAt' => null]),
            ]], '_links' => ['next' => null, 'previous' => null]]),
        ]);

        Livewire::actingAs(paymentAdmin())->test(Payments::class)->set('q', 'cst_only1')->assertSee('Keine Belege gefunden.')
            ->call('searchMollie')->assertSee('Nur Bei Mollie')->assertSee('mollie@example.tld')
            ->assertSee('(2)')->assertSee('tr_first1')->assertSee('tr_second2')->assertSee('Fehlgeschlagen');
    });

    it('takes over the current Mollie state of an unsettled record on request, and only of unsettled ones', function () {
        $open = MollieFixtures::intent(N::member(), 'membership', 'tr_admin_sync1');
        $settled = MollieFixtures::paidRecord(N::member());
        MollieFixtures::fake(MollieFixtures::resource($open, 'expired', ['paidAt' => null]));

        Livewire::actingAs(paymentAdmin())->test(Payments::class)->set('q', '#'.$open->id)
            ->assertSee('Jetzt mit Mollie abgleichen')->call('syncRecord', $open->id)
            ->assertSee('Beleg #'.$open->id.' abgeglichen: Abgelaufen.')->assertDontSee('Jetzt mit Mollie abgleichen');
        expect($open->fresh()->status)->toBe('expired');

        Livewire::actingAs(paymentAdmin())->test(Payments::class)->call('syncRecord', $settled->id)->assertNotFound();
    });

    it('refuses lookups for non-admins even through a direct Livewire call', function () {
        $record = MollieFixtures::paidRecord(N::member());
        Livewire::actingAs(N::member())->test(Payments::class)->assertForbidden();
        expect($record->fresh())->not->toBeNull();
    });
});
