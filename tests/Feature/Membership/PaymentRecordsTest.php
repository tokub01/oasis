<?php

/* Payment records are accounting documents (§ 147 AO, § 257 HGB): they survive account deletion and are pruned after
 * the statutory period. The admin payment search is tested in AdminPaymentSearchTest. */

use App\Actions\Account\DeleteAccount;
use App\Models\MembershipPayment;
use Tests\Support\MollieFixtures;
use Tests\Support\NetworkFixtures as N;

beforeEach(fn () => $this->withoutVite());

describe('keeping payment records', function () {
    it('keeps paid records with their invoice data when the account is deleted, without the account link', function () {
        $member = N::member();
        $record = MollieFixtures::paidRecord($member);

        app(DeleteAccount::class)->handle($member);

        $record->refresh();
        expect($record->user_id)->toBeNull()
            ->and($record->invoice_data['customer_email'])->toBe($member->email)
            ->and($record->mollie_customer_id)->toBe('cst_member'.$member->id);
    });

    it('prunes paid records after the period from the end of their year and unpaid attempts after 90 days', function () {
        config(['oasis.privacy.payment_records_years' => 8]);
        $member = N::member();
        $expired = MollieFixtures::paidRecord($member, ['mollie_payment_id' => 'tr_old', 'paid_at' => now()->subYears(8)->startOfYear()->subDay()]);
        $kept = MollieFixtures::paidRecord($member, ['mollie_payment_id' => 'tr_kept', 'paid_at' => now()->subYears(8)->startOfYear()->addDay()]);
        $attempt = MollieFixtures::paidRecord($member, ['mollie_payment_id' => 'tr_attempt', 'status' => 'expired', 'paid_at' => null]);
        $attempt->forceFill(['created_at' => now()->subDays(91)])->save();
        $recentAttempt = MollieFixtures::paidRecord($member, ['mollie_payment_id' => 'tr_recent', 'status' => 'open', 'paid_at' => null]);

        $this->artisan('model:prune', ['--model' => [MembershipPayment::class]])->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelMissing($attempt);
        $this->assertModelExists($kept);
        $this->assertModelExists($recentAttempt);
    });

    it('keeps every paid record while no period is configured', function () {
        config(['oasis.privacy.payment_records_years' => null]);
        $record = MollieFixtures::paidRecord(N::member(), ['paid_at' => now()->subYears(30)]);

        $this->artisan('model:prune', ['--model' => [MembershipPayment::class]])->assertSuccessful();

        $this->assertModelExists($record);
    });
});
