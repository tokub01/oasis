<?php

namespace App\Console\Commands;

use App\Services\Membership\StripeMembershipService;
use Illuminate\Console\Command;

/**
 * Artisan command to idempotently create and sync products and prices in Stripe test mode.
 */
class StripeSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oasis:stripe-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Oasis membership tiers and recurring prices to Stripe';

    /**
     * Execute the console command.
     */
    public function handle(StripeMembershipService $service): int
    {
        $this->info('Synchronizing Oasis membership tiers with Stripe...');

        try {
            $synced = $service->syncProductsAndPrices();

            $rows = [];
            foreach ($synced as $tier => $data) {
                $rows[] = [
                    $tier,
                    $data['product_id'],
                    $data['monthly_price_id'],
                    $data['yearly_price_id'],
                ];
            }

            $this->table(['Tier', 'Product ID', 'Monthly Price', 'Yearly Price'], $rows);
            $this->info('Stripe products and prices synchronized successfully.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to sync products with Stripe: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
