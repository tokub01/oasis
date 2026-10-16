<?php

namespace App\Livewire\Membership;

use App\Exceptions\Membership\MembershipDomainException;
use App\Livewire\Concerns\LimitsAttempts;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public membership selection and live order summary on /mitglied-werden (route oasis.mitglied).
 *
 * Replaces the former client-side JS module `membership`: tier, billing rhythm, payment method and custom Mäzen
 * contribution update the order summary reactively on the server. Returns 404 while `oasis.checkout_enabled` is false.
 */
class Checkout extends Component
{
    use LimitsAttempts;

    /** Selected membership tier key ('solidar', 'oase', 'maezen'). */
    public string $stufe = 'oase';

    /** Selected billing rhythm ('monat', 'jahr'). */
    public string $rhythmus = 'monat';

    /** Selected payment method ('sepa', 'paypal', 'karte'). */
    public string $zahlart = 'karte';

    /** Custom monthly Mäzen contribution in euros. */
    public ?string $betrag = null;

    /** Error banner when checkout initiation fails, announced via role="alert". */
    #[Locked]
    public string $error = '';

    /**
     * Guard the page behind the `oasis.checkout_enabled` feature flag and initialize defaults from config.
     */
    public function mount(): void
    {
        abort_unless(config('oasis.checkout_enabled'), 404);

        /** @var array<string, array<string, mixed>> $tiers */
        $tiers = config('oasis.tiers', []);
        $this->stufe = (string) (collect($tiers)->search(fn (array $tier): bool => (bool) ($tier['default'] ?? false)) ?: 'oase');
        $this->rhythmus = 'monat';
        $this->zahlart = 'karte';
        $this->betrag = (string) config('oasis.tiers.maezen.monthly', 12);
    }

    /**
     * Automatically switch to the Mäzen tier when the member focuses the custom amount input.
     */
    public function selectMaezen(): void
    {
        $this->stufe = 'maezen';
    }

    /**
     * Automatically switch to the Mäzen tier when the custom amount input changes.
     */
    public function updatedBetrag(): void
    {
        $this->stufe = 'maezen';
    }

    /**
     * Validate the chosen membership configuration and start the Mollie hosted checkout session.
     * Guests are guided to account registration first.
     */
    public function checkout(MollieMembershipService $service): void
    {
        abort_unless(config('oasis.checkout_enabled'), 404);
        $this->error = '';

        $minMaezen = (int) config('oasis.tiers.maezen.monthly', 12);

        $validated = $this->validate([
            'stufe' => ['required', 'string', Rule::in(['solidar', 'oase', 'maezen'])],
            'rhythmus' => ['required', 'string', Rule::in(['monat', 'jahr'])],
            'betrag' => [Rule::excludeIf($this->stufe !== 'maezen'), 'required', 'integer', 'min:'.$minMaezen],
            'zahlart' => ['nullable', 'string', Rule::in(['sepa', 'paypal', 'karte'])],
        ], [
            'stufe.required' => 'Bitte wähle eine Mitgliedschaftsstufe aus.',
            'rhythmus.required' => 'Bitte wähle deine Zahlweise aus.',
            'betrag.integer' => 'Bitte gib deinen Mäzen-Beitrag in ganzen Euro ein.',
            'betrag.required' => 'Bitte gib deinen Mäzen-Beitrag in ganzen Euro ein.',
            'betrag.min' => "Der Mindestbeitrag für Mäzen beträgt {$minMaezen} € im Monat.",
        ]);

        $user = auth()->user();

        if (! $user) {
            session()->flash('status', 'Erstelle bitte zuerst dein Oasis-Konto, um deine Mitgliedschaft abzuschließen.');
            $this->redirectRoute('register', navigate: true);

            return;
        }

        // Every attempt creates an order and a payment at Mollie.
        $this->limitAttempts('membership-checkout|'.$user->id, 5);
        $paymentMethod = $validated['zahlart'] ?: 'sepa';
        $customAmount = ($validated['stufe'] === 'maezen')
            ? (int) $validated['betrag']
            : null;

        try {
            $checkoutUrl = $service->createSubscriptionCheckout(
                $user,
                $validated['stufe'],
                $validated['rhythmus'],
                $customAmount,
                $paymentMethod
            );

            $this->redirect($checkoutUrl);
        } catch (MembershipDomainException $e) {
            // A clear reason from the domain, e.g. "Du hast bereits eine laufende Mitgliedschaft."
            $this->error = $e->getMessage();
        } catch (\Throwable $e) {
            Log::error('Mollie checkout initiation failed.', [
                'exception_class' => $e::class,
            ]);

            $this->error = 'Der Bezahlvorgang konnte leider nicht gestartet werden. Bitte versuche es später noch einmal.';
        }
    }

    /**
     * Render the membership selection and reactive order summary.
     */
    public function render(): View
    {
        abort_unless(config('oasis.checkout_enabled'), 404);

        /** @var array<string, array<string, mixed>> $tiers */
        $tiers = config('oasis.tiers', []);
        $yearMonths = (int) config('oasis.year_months', 10);
        $vatRate = (float) config('oasis.vat_rate', 19);
        $minMaezen = (float) ($tiers['maezen']['monthly'] ?? 12);
        $defaultTier = (string) (collect($tiers)->search(fn (array $tier): bool => (bool) ($tier['default'] ?? false)) ?: 'oase');

        $selectedStufe = array_key_exists($this->stufe, $tiers) ? $this->stufe : $defaultTier;
        $selectedRhythmus = in_array($this->rhythmus, ['monat', 'jahr'], true) ? $this->rhythmus : 'monat';
        $selectedZahlart = in_array($this->zahlart, ['sepa', 'paypal', 'karte'], true) ? $this->zahlart : 'karte';

        $parsedAmount = $this->parseMaezenAmount($this->betrag, $minMaezen);
        $monthlyAmount = $selectedStufe === 'maezen'
            ? $parsedAmount['monthly']
            : (float) ($tiers[$selectedStufe]['monthly'] ?? 5);

        $yearly = $selectedRhythmus === 'jahr';
        $summaryTotal = $yearly ? $monthlyAmount * $yearMonths : $monthlyAmount;
        $summaryVat = $summaryTotal * $vatRate / (100 + $vatRate);

        $user = auth()->user();
        $trialDaysLeft = $user?->trial_ends_at?->isFuture()
            ? (int) ceil(now()->diffInDays($user->trial_ends_at))
            : null;
        $trialEndDate = $user?->trial_ends_at?->timezone('Europe/Berlin')->format('d.m.Y');

        return view('livewire.membership.checkout', [
            'tiers' => $tiers,
            'yearMonths' => $yearMonths,
            'vatRate' => $vatRate,
            'minMaezen' => $minMaezen,
            'selectedStufe' => $selectedStufe,
            'selectedRhythmus' => $selectedRhythmus,
            'selectedZahlart' => $selectedZahlart,
            'yearly' => $yearly,
            'amountTooLow' => $parsedAmount['tooLow'],
            'amountInvalid' => $parsedAmount['invalid'],
            'summaryTotal' => $summaryTotal,
            'summaryVat' => $summaryVat,
            'trialDaysLeft' => $trialDaysLeft,
            'trialEndDate' => $trialEndDate,
        ])->layout('layouts.oasis', [
            'title' => 'Mitglied werden',
            'description' => 'Mitglied bei Oasis werden: Stufe, Zahlweise und Zahlungsart wählen. Alle Mitglieder bekommen dasselbe.',
            'minimal' => true,
            'noindex' => true,
        ]);
    }

    /**
     * Parse the raw Mäzen input and clamp amounts below the minimum for the live summary preview.
     *
     * @return array{monthly: float, tooLow: bool, invalid: bool}
     */
    private function parseMaezenAmount(?string $raw, float $min): array
    {
        $text = trim($raw ?? '');
        $integer = filter_var($text, FILTER_VALIDATE_INT);
        $valid = $integer !== false;
        $value = $valid ? (float) $integer : 0.0;

        return [
            'monthly' => ($valid && $value >= $min) ? round($value, 2) : $min,
            'tooLow' => $valid && $value < $min,
            'invalid' => ! $valid,
        ];
    }
}
