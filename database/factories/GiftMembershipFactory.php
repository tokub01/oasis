<?php

namespace Database\Factories;

use App\Models\GiftMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftMembership>
 */
class GiftMembershipFactory extends Factory
{
    protected $model = GiftMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => GiftMembership::generateCode(),
            'tier' => 'oase',
            'months' => 12,
            'amount' => 5000,
            'currency' => 'eur',
            'purchaser_user_id' => User::factory(),
            'purchaser_email' => fake()->safeEmail(),
            'mollie_payment_id' => 'cs_test_'.fake()->uuid(),
            'redeemed_by_user_id' => null,
            'redeemed_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * State: already redeemed.
     */
    public function redeemed(?User $recipient = null, int $months = 12): static
    {
        return $this->state(fn () => [
            'redeemed_by_user_id' => $recipient ?? User::factory(),
            'redeemed_at' => now(),
            'expires_at' => now()->addMonths($months),
        ]);
    }
}
