<?php

namespace App\Livewire\Admin;

use App\Exceptions\Membership\MembershipDomainException;
use App\Models\MembershipPayment;
use App\Services\Membership\MollieGateway;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mollie\Api\Exceptions\MollieException;
use Mollie\Api\Exceptions\NotFoundException;

/**
 * Payment search on /admin/zahlungen (route admin.payments): finds membership payments – also of deleted accounts,
 * whose records stay as accounting documents – by name, e-mail, username, record number or Mollie id, and looks up
 * a payment or customer live at Mollie (read only, with Mollie's own dashboard link).
 *
 * Purpose is bookkeeping and payment support only. Admins with 2FA (route middleware, checked again on every request);
 * every Mollie lookup is logged with the admin and the looked-up id, never with names or e-mail addresses.
 */
class Payments extends Component
{
    use WithPagination;

    /** Status filter values: Mollie statuses plus "erstattet" for refunded records. */
    public const STATUSES = ['paid' => 'Bezahlt', 'open' => 'Offen', 'pending' => 'In Bearbeitung', 'failed' => 'Fehlgeschlagen',
        'canceled' => 'Abgebrochen', 'expired' => 'Abgelaufen', 'erstattet' => 'Erstattet'];

    /** Mollie ids as they appear in the dashboard: tr_… (payment), cst_… (customer). */
    private const MOLLIE_ID = '/^(tr|cst)_[A-Za-z0-9]{4,40}$/';

    #[Url(as: 'suche')]
    public string $q = '';

    #[Url(as: 'status')]
    public string $status = '';

    /**
     * Result of the last Mollie lookup: kind payment|customer, the data, and for a customer its payments.
     *
     * @var array{kind: string, payment?: array<string, mixed>, customer?: array<string, mixed>, payments?: list<array<string, mixed>>}|null
     */
    #[Locked]
    public ?array $mollie = null;

    #[Locked]
    public ?string $mollieError = null;

    /** Result of the last "Jetzt abgleichen", announced through role="status". */
    #[Locked]
    public ?string $syncStatus = null;

    public function mount(): void
    {
        Gate::authorize('administrate');
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** Live state of one local record's payment at Mollie. */
    public function checkPayment(int $recordId, MollieGateway $gateway): void
    {
        $record = $this->record($recordId);
        abort_if($record->mollie_payment_id === null, 404);
        $this->lookup('payment', $record->mollie_payment_id, fn () => ['kind' => 'payment', 'payment' => $gateway->lookupPayment($record->mollie_payment_id)]);
    }

    /** The Mollie customer of one local record with their newest payments. */
    public function checkCustomer(int $recordId, MollieGateway $gateway): void
    {
        $record = $this->record($recordId);
        abort_if($record->mollie_customer_id === null, 404);
        $this->lookup('customer', $record->mollie_customer_id, fn () => $this->customer($gateway, $record->mollie_customer_id));
    }

    /**
     * Take over the current state of one unsettled record from Mollie now, exactly like the scheduled oasis:mollie-sync
     * (a paid membership is fulfilled, a deleted account's record only gets its status).
     */
    public function syncRecord(int $recordId, MollieMembershipService $service): void
    {
        $record = $this->record($recordId);
        abort_unless($record->isUnsettled(), 404);
        Log::info('Admin Mollie sync', ['admin_id' => auth()->id(), 'record_id' => $record->id, 'mollie_id' => $record->mollie_payment_id]);
        try {
            $service->processPayment((string) $record->mollie_payment_id);
            $record->refresh();
            $this->syncStatus = 'Beleg #'.$record->id.' abgeglichen: '.(self::STATUSES[$record->status] ?? $record->status)
                .($record->processed_at !== null ? ', verarbeitet.' : '.');
        } catch (MollieException|MembershipDomainException $failure) {
            Log::warning('Admin Mollie sync failed', ['record_id' => $record->id, 'exception' => $failure::class]);
            $this->syncStatus = 'Beleg #'.$record->id.' ließ sich nicht abgleichen. Die Angaben bei Mollie passen nicht zum Beleg oder Mollie ist nicht erreichbar.';
        }
    }

    /** Search Mollie directly for a tr_… or cst_… id typed into the search field, also when no local record has it. */
    public function searchMollie(MollieGateway $gateway): void
    {
        Gate::authorize('administrate');
        $id = trim($this->q);
        if (preg_match(self::MOLLIE_ID, $id) !== 1) {
            $this->mollie = null;
            $this->mollieError = 'Gib eine Mollie-ID ein, zum Beispiel tr_… für eine Zahlung oder cst_… für eine Kundin oder einen Kunden.';

            return;
        }
        str_starts_with($id, 'tr_')
            ? $this->lookup('payment', $id, fn () => ['kind' => 'payment', 'payment' => $gateway->lookupPayment($id)])
            : $this->lookup('customer', $id, fn () => $this->customer($gateway, $id));
    }

    public function closeMollie(): void
    {
        $this->mollie = null;
        $this->mollieError = null;
    }

    public function render(): View
    {
        Gate::authorize('administrate');

        return view('livewire.admin.payments', [
            'payments' => $this->query()->with('user')->latest('id')->paginate(25),
            'statuses' => self::STATUSES,
            'mollieId' => preg_match(self::MOLLIE_ID, trim($this->q)) === 1,
            'retention' => config('oasis.privacy.payment_records_years'),
        ])->layout('layouts.staff', ['title' => 'Zahlungen', 'active' => 'payments']);
    }

    /** @return Builder<MembershipPayment> */
    private function query(): Builder
    {
        $term = trim($this->q);

        return MembershipPayment::query()
            ->when($this->status === 'erstattet', fn (Builder $q) => $q->where('refunded', true))
            ->when(isset(self::STATUSES[$this->status]) && $this->status !== 'erstattet', fn (Builder $q) => $q->where('status', $this->status))
            ->when($term !== '', function (Builder $q) use ($term): void {
                if (preg_match('/^#?(\d{1,18})$/', $term, $number) === 1) {
                    $q->whereKey((int) $number[1]);

                    return;
                }
                if (preg_match(self::MOLLIE_ID, $term) === 1) {
                    $q->where(str_starts_with($term, 'tr_') ? 'mollie_payment_id' : 'mollie_customer_id', $term);

                    return;
                }
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $q->where(fn (Builder $any) => $any
                    ->where('invoice_data->customer_name', 'like', $like)
                    ->orWhere('invoice_data->customer_email', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', $like)
                        ->orWhere('display_name', 'like', $like)->orWhere('username', 'like', $like)));
            });
    }

    private function record(int $id): MembershipPayment
    {
        Gate::authorize('administrate');

        return MembershipPayment::findOrFail($id);
    }

    /**
     * @return array{kind: string, customer: array<string, mixed>, payments: list<array<string, mixed>>}
     */
    private function customer(MollieGateway $gateway, string $id): array
    {
        return ['kind' => 'customer', 'customer' => $gateway->lookupCustomer($id), 'payments' => $gateway->customerPayments($id)];
    }

    /** Run one read-only Mollie request; failures become a German message, details stay in the log. */
    private function lookup(string $kind, string $id, callable $request): void
    {
        Log::info('Admin Mollie lookup', ['admin_id' => auth()->id(), 'kind' => $kind, 'mollie_id' => $id]);
        $this->mollie = null;
        $this->mollieError = null;
        try {
            $this->mollie = $request();
        } catch (NotFoundException) {
            $this->mollieError = 'Bei Mollie gibt es '.($kind === 'payment' ? 'keine Zahlung' : 'keine Kundin und keinen Kunden').' mit der ID '.$id.'.';
        } catch (MollieException $failure) {
            Log::warning('Admin Mollie lookup failed', ['mollie_id' => $id, 'exception' => $failure::class]);
            $this->mollieError = 'Mollie ist gerade nicht erreichbar oder hat die Anfrage abgelehnt. Bitte später noch einmal versuchen.';
        }
    }
}
