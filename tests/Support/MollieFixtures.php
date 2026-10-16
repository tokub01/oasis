<?php

namespace Tests\Support;

use App\Models\MembershipPayment;
use App\Models\User;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Fake\SequenceMockResponse;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Laravel\Facades\Mollie;

/** Server-owned checkout orders and authentic SDK response fakes for membership regression tests. */
class MollieFixtures
{
    /** Create a pending purchase with no access or voucher yet. */
    public static function intent(User $user, string $kind = 'gift', string $id = 'tr_test123', array $attributes = []): MembershipPayment
    {
        $user->update(['mollie_customer_id' => 'cst_'.$user->id]);

        return MembershipPayment::create(array_merge(['user_id' => $user->id, 'mollie_payment_id' => $id,
            'kind' => $kind, 'tier' => 'oase', 'rhythm' => $kind === 'gift' ? 'jahr' : 'monat',
            'amount' => $kind === 'gift' ? 5000 : 500, 'status' => 'open'], $attributes));
    }

    /** API resource for an order; submitted webhook bodies cannot override these fields. */
    public static function resource(MembershipPayment $intent, ?string $status = 'paid', array $attributes = []): array
    {
        return array_replace(['resource' => 'payment', 'id' => $intent->mollie_payment_id, 'status' => $status,
            'customerId' => User::findOrFail($intent->user_id)->mollie_customer_id,
            'amount' => ['currency' => 'EUR', 'value' => number_format($intent->amount / 100, 2, '.', '')],
            'paidAt' => now()->toIso8601String(), 'mandateId' => 'mdt_test123'], $attributes);
    }

    /** Fake only the expected payment API; unexpected calls fail instead of reaching the network. */
    public static function fake(array ...$payments): void
    {
        Mollie::fake([GetPaymentRequest::class => new SequenceMockResponse(...array_map(fn ($payment) => MockResponse::ok($payment), $payments))]);
    }
}
