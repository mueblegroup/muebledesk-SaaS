<?php

namespace Tests\Feature\Platform;

use App\Models\PlatformWebhookEvent;
use App\Services\StripePlatformBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_stripe_event_is_recorded_and_processed_only_once(): void
    {
        $event = [
            'id' => 'evt_idempotency_test',
            'type' => 'muebledesk.test.noop',
            'data' => ['object' => []],
        ];

        $stripe = Mockery::mock(StripePlatformBillingService::class);
        $stripe->shouldReceive('verifyWebhook')
            ->twice()
            ->andReturn($event);
        $this->app->instance(StripePlatformBillingService::class, $stripe);

        $payload = json_encode($event, JSON_THROW_ON_ERROR);

        $this->call('POST', route('stripe.platform.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'test-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->call('POST', route('stripe.platform.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'test-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame(1, PlatformWebhookEvent::query()
            ->where('provider', 'stripe')
            ->where('event_id', 'evt_idempotency_test')
            ->count());

        $this->assertDatabaseHas('platform_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_idempotency_test',
            'status' => 'processed',
        ]);
    }
}
