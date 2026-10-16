<?php

namespace App\Console\Commands;

use App\Models\MembershipPayment;
use App\Models\Subscription;
use App\Services\Membership\MollieGateway;
use App\Services\Membership\MollieMembershipService;
use Illuminate\Console\Command;

/** Repairs missed payment notifications and reconciles remote recurring agreements. Prices live in oasis.tiers. */
class MollieSyncCommand extends Command
{
    protected $signature = 'oasis:mollie-sync';

    protected $description = 'Offene Mollie-Zahlungen und Abonnements abgleichen';

    /** Retry unprocessed orders and recognize provider-side cancellations without extending unpaid access. */
    public function handle(MollieMembershipService $service, MollieGateway $gateway): int
    {
        $failed = false;
        foreach (MembershipPayment::whereNull('processed_at')->whereNotNull('mollie_payment_id')->cursor() as $payment) {
            try {
                $service->processPayment($payment->mollie_payment_id);
            } catch (\Throwable $e) {
                $failed = true;
                $this->error('Zahlungsabgleich fehlgeschlagen: '.$payment->id.' ('.$e::class.')');
            }
        }
        foreach (Subscription::whereNotNull('mollie_id')->with('user')->cursor() as $subscription) {
            try {
                foreach ($gateway->subscriptionPayments($subscription->user->mollie_customer_id, $subscription->mollie_id) as $id) {
                    $service->processPayment($id);
                }
                $subscription->refresh();
                $status = $gateway->subscriptionStatus($subscription->user->mollie_customer_id, $subscription->mollie_id);
                if ($subscription->ends_at === null && in_array($status, ['canceled', 'completed'], true)) {
                    $subscription->update(['status' => 'canceled', 'ends_at' => $subscription->paid_until]);
                }
            } catch (\Throwable $e) {
                $failed = true;
                $this->error('Abo-Abgleich fehlgeschlagen: '.$subscription->id.' ('.$e::class.')');
            }
        }
        $this->info('Mollie-Abgleich abgeschlossen.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
