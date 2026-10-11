<?php

use App\Services\Membership\StripeMembershipService;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
use Stripe\StripeClient;

test('stripe-sync command synchronizes tiers and prices to Stripe in test mode', function () {
    $stripeMock = Mockery::mock(StripeClient::class);
    $productService = Mockery::mock(ProductService::class);
    $priceService = Mockery::mock(PriceService::class);

    $stripeMock->shouldReceive('__get')->with('products')->andReturn($productService);
    $stripeMock->shouldReceive('__get')->with('prices')->andReturn($priceService);
    $stripeMock->products = $productService;
    $stripeMock->prices = $priceService;

    // Simulate search returns empty (no existing products)
    $productService->shouldReceive('search')
        ->times(3)
        ->andReturn((object) ['data' => []]);

    // Expect create for solidar, oase, maezen
    $productService->shouldReceive('create')
        ->times(3)
        ->andReturnUsing(function ($params) {
            return (object) ['id' => 'prod_test_'.$params['metadata']['oasis_tier']];
        });

    // Simulate price lookup returns empty (no existing prices)
    $priceService->shouldReceive('all')
        ->times(6)
        ->andReturn((object) ['data' => []]);

    // Expect create for 3 tiers x 2 intervals (monthly, yearly) = 6 prices
    $priceService->shouldReceive('create')
        ->times(6)
        ->andReturnUsing(function ($params) {
            return (object) ['id' => 'price_test_'.$params['lookup_key']];
        });

    $this->app->instance(StripeClient::class, $stripeMock);

    $this->artisan('oasis:stripe-sync')
        ->expectsOutputToContain('Stripe products and prices synchronized successfully')
        ->assertSuccessful();
});

test('getPriceIdForTier returns existing price or generates on-demand for custom maezen amount', function () {
    $stripeMock = Mockery::mock(StripeClient::class);
    $priceService = Mockery::mock(PriceService::class);
    $productService = Mockery::mock(ProductService::class);

    $stripeMock->products = $productService;
    $stripeMock->prices = $priceService;

    $this->app->instance(StripeClient::class, $stripeMock);

    // Case 1: Existing price
    $priceService->shouldReceive('all')
        ->once()
        ->with(['lookup_keys' => ['oasis_oase_monthly'], 'limit' => 1])
        ->andReturn((object) ['data' => [(object) ['id' => 'price_existing_oase_m']]]);

    $service = app(StripeMembershipService::class);
    $priceId = $service->getPriceIdForTier('oase', 'monat');
    expect($priceId)->toBe('price_existing_oase_m');

    // Case 2: Custom Mäzen amount with new price creation
    $priceService->shouldReceive('all')
        ->once()
        ->with(['lookup_keys' => ['oasis_maezen_monthly_35'], 'limit' => 1])
        ->andReturn((object) ['data' => []]);

    $productService->shouldReceive('search')
        ->times(3)
        ->andReturn((object) ['data' => [(object) ['id' => 'prod_maezen_123']]]);

    $priceService->shouldReceive('all')
        ->times(6)
        ->andReturn((object) ['data' => [(object) ['id' => 'price_sync_123']]]);

    $priceService->shouldReceive('create')
        ->once()
        ->with(Mockery::on(function ($params) {
            return $params['product'] === 'prod_maezen_123'
                && $params['unit_amount'] === 3500
                && $params['lookup_key'] === 'oasis_maezen_monthly_35';
        }))
        ->andReturn((object) ['id' => 'price_custom_maezen_35']);

    $maezenPriceId = $service->getPriceIdForTier('maezen', 'monat', 35);
    expect($maezenPriceId)->toBe('price_custom_maezen_35');
});
