<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\PayPalWebhookController;
use App\Models\PayPalWebhookEvent;
use Illuminate\Console\Command;

class RecoverPayPalWebhook extends Command
{
    protected $signature = 'payments:recover-paypal-webhook {event-id : Local paypal_webhook_events database ID}';

    protected $description = 'Recover one recorded but unprocessed PayPal webhook event';

    public function handle(PayPalWebhookController $controller): int
    {
        $eventId = $this->argument('event-id');
        $event = PayPalWebhookEvent::query()->find($eventId);

        if (! $event) {
            $this->error('PayPal webhook event was not found.');

            return self::FAILURE;
        }

        $this->info("Recovering PayPal webhook event {$event->id}...");
        $this->line('Event type: '.$event->event_type);
        $this->line('Resource ID: '.($event->resource_id ?: 'Not available'));
        $this->line('Created at: '.($event->created_at?->toISOString() ?? 'Not available'));

        try {
            $controller->recover($event);
        } catch (\Throwable) {
            $this->error('PayPal webhook recovery failed.');
            $this->line('The event remains recorded and unprocessed unless another worker completed it.');
            $this->line('Review the payments_process log for details.');

            return self::FAILURE;
        }

        $this->info('Recovery completed successfully.');

        return self::SUCCESS;
    }
}
