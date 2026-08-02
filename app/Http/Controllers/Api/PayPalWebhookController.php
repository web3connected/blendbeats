<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayPalWebhookEvent;
use App\Models\PaymentProvider;
use App\Models\User;
use App\Services\AffiliateReferralQualificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalWebhookController extends Controller
{
    protected string $stage = '';
    protected string $status = 'pending';
    protected Request $request;
    protected string $rawPayload = '';
    protected array $payload = [];
    protected array $headers = [];
    protected array $result = [];
    protected int $replayWindowSeconds = 300;
    protected ?PaymentProvider $paymentProvider = null;
    protected string $provider = 'paypal';
    protected ?string $eventId = null;
    protected ?string $eventType = null;
    protected ?string $resourceId = null;
    protected ?string $transmissionId = null;
    protected ?string $transmissionTime = null;
    protected ?string $webhookId = null;
    protected ?string $paypalClientId = null;
    protected ?string $paypalSecret = null;
    protected string $paypalMode = '';
    protected ?PayPalWebhookEvent $webhookEvent = null;
    protected ?User $user = null;
    protected bool $shouldProcess = true;
    protected ?Lock $processingLock = null;
    protected bool $processingLockAcquired = false;
    protected ?string $processingLockKey = null;
    protected bool $recoveryMode = false;

    public function __construct(private readonly AffiliateReferralQualificationService $referralQualification)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $this->request = $request;

        try {
            // Initialize stage: load PayPal provider configuration and confirm the webhook can proceed.
            $this->stage = 'initialize';
            $this->initialize();

            // Receive stage: capture the raw request, normalize the payload, and extract event identifiers.
            $this->stage = 'receive';
            $this->receive();

            // Validate stage: ensure required webhook fields exist and run the signature stub.
            $this->stage = 'validate';
            $this->validate();

            // Protect stage: detect duplicates, replay attempts, and preserve ownership protection.
            $this->stage = 'protect';
            $this->protect();

            // Record stage: persist the raw webhook payload before business processing.
            $this->stage = 'record';
            $this->record();

            // Process stage: execute the existing PayPal subscription business logic.
            $this->stage = 'process';
            $this->process();

            // Respond stage: return the expected webhook acknowledgement.
            $this->stage = 'respond';
            return $this->respond();
        } catch (\Throwable $exception) {
            $this->logStageError($exception);

            return response()->json([
                'received' => false,
            ], 500);
        } finally {
            $this->releaseProcessingLock();
        }
    }

    public function recover(PayPalWebhookEvent $webhookEvent): void
    {
        $this->reconstructRecoveryState($webhookEvent);

        if ($webhookEvent->processed_at !== null) {
            $this->logStageInfo('PayPal webhook recovery skipped because event is already processed', [
                'recovery_mode' => true,
                'attempt_result' => 'already_processed',
                'current_processed_state' => true,
            ]);

            throw new RuntimeException('PayPal webhook event is already processed.');
        }

        $lock = Cache::lock('paypal-webhook-recovery:'.$webhookEvent->getKey(), 60);

        if (! $lock->get()) {
            $this->logStageWarning('PayPal webhook recovery skipped because another worker owns the event', [
                'recovery_mode' => true,
                'attempt_result' => 'lock_unavailable',
                'current_processed_state' => false,
            ]);

            throw new RuntimeException('PayPal webhook event is already being recovered.');
        }

        try {
            $webhookEvent->refresh();
            $this->reconstructRecoveryState($webhookEvent);

            if ($webhookEvent->processed_at !== null) {
                $this->logStageInfo('PayPal webhook recovery skipped because event is already processed', [
                    'recovery_mode' => true,
                    'attempt_result' => 'already_processed_after_lock',
                    'current_processed_state' => true,
                ]);

                throw new RuntimeException('PayPal webhook event is already processed.');
            }

            $this->assertRecoverableEvent();
            $this->loadUserFromPayload();
            $this->shouldProcess = ! ($this->user && $this->user->billing_provider !== 'paypal');

            $this->logStageInfo('PayPal webhook recovery started', [
                'recovery_mode' => true,
                'attempt_result' => 'started',
                'current_processed_state' => false,
            ]);

            $this->process();

            $this->logStageInfo('PayPal webhook recovery completed', [
                'recovery_mode' => true,
                'attempt_result' => 'completed',
                'current_processed_state' => true,
            ]);
        } catch (\Throwable $exception) {
            if ($exception->getMessage() !== 'PayPal webhook event is already processed.') {
                $this->logStageError($exception, [
                    'recovery_mode' => true,
                    'attempt_result' => 'failed',
                    'current_processed_state' => false,
                ]);
            }

            throw $exception;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $exception) {
                $this->logStageWarning('PayPal webhook recovery lock could not be released cleanly', [
                    'recovery_mode' => true,
                    'attempt_result' => 'lock_release_failed',
                    'exception_class' => get_class($exception),
                ]);
            }
        }
    }

    protected function reconstructRecoveryState(PayPalWebhookEvent $webhookEvent): void
    {
        $payload = $webhookEvent->payload;

        $this->recoveryMode = true;
        $this->stage = 'process';
        $this->status = 'recorded';
        $this->provider = 'paypal';
        $this->webhookEvent = $webhookEvent;
        $this->payload = is_array($payload) ? $payload : [];
        $this->eventId = is_string($this->payload['id'] ?? null) ? $this->payload['id'] : null;
        $this->eventType = $webhookEvent->event_type;
        $this->resourceId = $webhookEvent->resource_id;
        $this->user = null;
        $this->shouldProcess = true;
    }

    protected function assertRecoverableEvent(): void
    {
        $missing = [];

        if (! $this->eventType) {
            $missing[] = 'event_type';
        }

        if (! $this->resourceId) {
            $missing[] = 'resource_id';
        }

        if ($this->payload === []) {
            $missing[] = 'payload';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'PayPal webhook event is not recoverable. Missing: '.implode(', ', $missing).'.'
            );
        }
    }

    protected function initialize(): void
    {
        $readiness = PaymentProvider::paypalReadiness();

        if (! $readiness['webhook_receipt']['ready']) {
            throw new RuntimeException(
                'PayPal configuration is incomplete for the selected environment. Missing: '
                .implode(', ', $readiness['webhook_receipt']['missing']).'.'
            );
        }

        // Laravel configuration is the canonical authority shared with
        // BillingController. PaymentProvider remains status/administrative metadata.
        $paypal = PaymentProvider::paypalConfiguration();
        $this->paypalMode = $paypal['mode'];
        $this->paypalClientId = $paypal['client_id'];
        $this->paypalSecret = $paypal['secret'];
        $this->webhookId = $paypal['webhook_id'];

        $this->paymentProvider = PaymentProvider::query()
            ->where('provider', $this->provider)
            ->first();

        if (! $this->paymentProvider) {
            $this->paymentProvider = new PaymentProvider([
                'provider' => $this->provider,
            ]);
        }

        if ($this->shouldEnforceWebhookSignature()) {
            if (! $this->paypalClientId || ! $this->paypalSecret) {
                throw new RuntimeException(
                    'PayPal webhook verification requires PayPal client credentials.'
                );
            }

            if (! $this->webhookId) {
                throw new RuntimeException(
                    'PayPal webhook verification requires a PayPal webhook ID.'
                );
            }
        }

        $replayWindowSeconds = config('billing.paypal.replay_window_seconds', 300);

        if (! is_int($replayWindowSeconds) && ! ctype_digit((string) $replayWindowSeconds)) {
            throw new RuntimeException(
                'PayPal replay window configuration must be a positive integer.'
            );
        }

        $replayWindowSeconds = (int) $replayWindowSeconds;

        if ($replayWindowSeconds <= 0) {
            throw new RuntimeException(
                'PayPal replay window configuration must be a positive integer.'
            );
        }

        $this->replayWindowSeconds = $replayWindowSeconds;
        $this->status = 'initialized';
    }

    protected function receive(): void
    {
        $this->rawPayload = $this->request->getContent();
        $this->payload = $this->request->all();
        $this->headers = $this->request->headers->all();

        // The PayPal event ID is the webhook event identifier from the JSON payload.
        $this->eventId = is_string($this->payload['id'] ?? null) ? $this->payload['id'] : null;

        // The resource ID is the PayPal subscription or resource identifier from resource.id.
        $this->eventType = is_string($this->payload['event_type'] ?? null) ? $this->payload['event_type'] : null;
        $this->resourceId = is_string($this->payload['resource']['id'] ?? null) ? $this->payload['resource']['id'] : null;

        // Normalized PayPal transmission metadata from HTTP headers.
        $this->transmissionId = $this->request->header('PAYPAL-TRANSMISSION-ID');
        $this->transmissionTime = $this->request->header('PAYPAL-TRANSMISSION-TIME');

        $this->status = 'received';
    }

    protected function validate(): void
    {
        if (empty($this->payload)) {
            throw new RuntimeException(
                'PayPal webhook payload is missing or invalid.'
            );
        }

        if (empty($this->eventType)) {
            throw new RuntimeException(
                'PayPal webhook event type is missing.'
            );
        }

        if (empty($this->resourceId)) {
            throw new RuntimeException(
                'PayPal webhook resource identifier is missing.'
            );
        }

        if ($this->provider !== 'paypal') {
            throw new RuntimeException(
                'PayPal webhook provider is not recognized.'
            );
        }

        if ($this->shouldEnforceWebhookSignature()) {
            $headerReference = $this->getHeaderReference();

            foreach ($headerReference['required_headers'] as $requiredHeader) {
                if (! $this->request->hasHeader($requiredHeader)) {
                    throw new RuntimeException(
                        "Missing required PayPal webhook header: {$requiredHeader}"
                    );
                }
            }
        }

        if (! $this->validateSignature()) {
            throw new RuntimeException(
                'PayPal webhook signature validation failed.'
            );
        }

        $this->status = 'validated';
    }

    protected function validateSignature(): bool
    {
        /*
         * Signature verification has been implemented,
         * but enforcement is controlled by configuration until the complete workflow is finished.
         */
        if (! $this->shouldEnforceWebhookSignature()) {
            return true;
        }

        if (! $this->paymentProvider) {
            throw new RuntimeException(
                'PayPal provider configuration is required for signature verification.'
            );
        }

        if (! $this->webhookId) {
            throw new RuntimeException(
                'PayPal webhook ID is required for signature verification.'
            );
        }

        if ($this->rawPayload === '') {
            throw new RuntimeException(
                'PayPal webhook raw payload is required for signature verification.'
            );
        }

        return $this->verifyWebhookSignatureWithPayPal();
    }

    protected function protect(): void
    {
        // Confirm normalized identifiers are available.
        // Normalization happens during receive(), but Protect evaluates the values.

        // Load the user associated with the PayPal resource ID.
        $this->loadUserFromPayload();

        // Ownership protection: ignore non-PayPal users.
        if ($this->user && $this->user->billing_provider !== 'paypal') {
            $this->shouldProcess = false;
            $this->logStageInfo('PayPal webhook ignored for non-PayPal billing provider user', [
                'user_id' => $this->user->id,
                'billing_provider' => $this->user->billing_provider,
            ]);
        }

        // Duplicate Protection
        $duplicate = $this->duplicateEventCheck();

        if ($duplicate['detected']) {
            $this->logStageWarning(
                'Possible duplicate PayPal webhook event detected.',
                [
                    'duplicate_check_mode' => $duplicate['enforceable']
                        ? 'enforcing' : 'diagnostic_only',
                    'duplicate_identifier_type' => $duplicate['identifier_type'],
                    'duplicate_identifier' => $duplicate['identifier'],
                    'duplicate_enforceable' => $duplicate['enforceable'],
                    'duplicate_reason' => $duplicate['reason'],
                ]
            );

            if ($duplicate['detected'] && $duplicate['enforceable']) {
                throw new RuntimeException(
                    'Duplicate PayPal webhook event detected.'
                );
            }
        }

        // Replay Protection
        $replay = $this->replayCheck();

        if ($replay['detected']) {
            $this->logStageWarning(
                'Possible replayed PayPal webhook detected.',
                [
                    'replay_check_mode' => $replay['enforceable']
                        ? 'enforcing' : 'diagnostic_only',
                    'replay_reason' => $replay['reason'],
                    'replay_age_seconds' => $replay['age_seconds'],
                    'transmission_id' => $this->transmissionId,
                ]
            );

            if ($replay['enforceable']) {
                throw new RuntimeException(
                    'Possible replayed PayPal webhook detected.'
                );
            }
        }

        // Business-State Protection
        if (! $this->canProcessEvent()) {
            throw new RuntimeException(
                'PayPal webhook event cannot be processed for the current business state.'
            );
        }

        // Concurrency Protection
        $lockAcquired = $this->acquireProcessingLock();

        if (! $lockAcquired) {
            $this->logStageWarning(
                'PayPal webhook processing lock could not be acquired.',
                [
                    'processing_lock_mode' => $this->shouldEnforceProcessingLock()
                        ? 'enforcing'
                        : 'diagnostic_only',
                ]
            );

            if ($this->shouldEnforceProcessingLock()) {
                throw new RuntimeException(
                    'Unable to acquire PayPal webhook processing lock.'
                );
            }
        }

        $this->status = 'protected';
    }

    protected function record(): void
    {
        if (! $this->eventType || ! $this->resourceId) {
            throw new RuntimeException(
                'PayPal webhook cannot be recorded without normalized event_type and resource_id.'
            );
        }

        $this->webhookEvent = PayPalWebhookEvent::create([
            'event_type' => $this->eventType,
            'resource_id' => $this->resourceId,
            'payload' => $this->payload,
        ]);

        $this->status = 'recorded';

        $this->logStageInfo('PayPal webhook event recorded', [
            'webhook_event_id' => $this->webhookEvent->id,
        ]);
    }

    protected function process(): void
    {
        $this->result = [
            'processed' => false,
            'user_updated' => false,
            'affiliate_qualified' => false,
            'subscription_status' => null,
            'user_found' => false,
            'ownership_guarded' => ! $this->shouldProcess,
            'unknown_event' => false,
        ];

        try {
            DB::transaction(function (): void {
                if ($this->shouldProcess) {
                    $this->processSubscription();
                }

                // processed_at participates in the same transaction as all business
                // writes. The audit row itself was created before this boundary.
                $this->markWebhookEventProcessed();
                $this->result['processed'] = true;
            });
        } catch (\Throwable $exception) {
            // Eloquent objects are not automatically reverted in memory when the
            // database rolls back. Keep recovery logging aligned with committed state.
            $this->result['processed'] = false;
            $this->result['user_updated'] = false;
            $this->result['affiliate_qualified'] = false;
            $this->result['subscription_status'] = null;

            throw $exception;
        }

        $this->webhookEvent?->refresh();

        $this->status = 'processed';

        $message = $this->shouldProcess
            ? 'PayPal webhook business processing completed'
            : 'PayPal webhook business processing skipped by ownership guard';

        $this->logStageInfo($message, [
            'result' => $this->result,
        ]);
    }

    protected function respond(): JsonResponse
    {
        $this->status = 'completed';

        // Respond is intentionally the terminal stage for this workflow.
        // Future follow-up actions may be dispatched from this stage,
        // such as user notifications, business-team notifications,
        // accounting dispatch, or background job scheduling.
        $this->logStageInfo('PayPal webhook completed successfully', [
            'webhook_event_id' => $this->webhookEvent?->id,
            'result' => $this->result,
        ]);

        return response()->json([
            'received' => true,
        ]);
    }

    protected function loadUserFromPayload(): void
    {
        if (! $this->resourceId) {
            return;
        }

        $this->user = User::where('paypal_subscription_id', $this->resourceId)->first();
    }

    protected function duplicateEventCheck(): array
    {
        $result = [
            'detected' => false,
            'identifier_type' => null,
            'identifier' => null,
            'enforceable' => false,
            'mode' => 'diagnostic_only',
            'reason' => null,
        ];

        if ($this->eventId) {
            $result['identifier_type'] = 'event_id';
            $result['identifier'] = $this->eventId;
            $result['reason'] = 'event_id';
            $result['mode'] = 'potentially_enforceable';
            $result['enforceable'] = $this->shouldEnforceDuplicateProtection();

            try {
                $exists = PayPalWebhookEvent::query()
                    ->whereJsonContains('payload->id', $this->eventId)
                    ->exists();

                if ($exists) {
                    $result['detected'] = true;
                    return $result;
                }
            } catch (\Throwable $exception) {
                // JSON payload querying may not be supported by all drivers or the current schema.
                $result['identifier_type'] = 'event_id_unavailable';
                $result['identifier'] = $this->eventId;
                $result['reason'] = 'event_id_unavailable';
                $result['enforceable'] = false;
                $result['mode'] = 'diagnostic_only';
            }
        }

        if ($this->transmissionId) {
            $result['identifier_type'] = 'transmission_id';
            $result['identifier'] = $this->transmissionId;
            $result['reason'] = 'transmission_id';
            $result['mode'] = 'diagnostic_only';
            $result['enforceable'] = false;

            // The existing schema currently does not persist HTTP transmission IDs.
            // If the application later stores transmission_id separately, this check can be upgraded.
        }

        if (! $result['detected'] && $this->eventType && $this->resourceId) {
            $result['identifier_type'] = 'event_type+resource_id';
            $result['identifier'] = $this->eventType.':'.$this->resourceId;
            $result['reason'] = 'event_type+resource_id';
            $result['mode'] = 'diagnostic_only';
            $result['enforceable'] = false;

            $result['detected'] = PayPalWebhookEvent::query()
                ->where('event_type', $this->eventType)
                ->where('resource_id', $this->resourceId)
                ->whereNotNull('processed_at')
                ->exists();
        }

        return $result;
    }

    protected function processSubscription(): void
    {
        $this->result['subscription_status'] = null;
        $this->result['user_found'] = false;
        $this->result['unknown_event'] = false;

        if (! $this->eventType || ! $this->resourceId) {
            return;
        }

        if (! $this->user) {
            $this->result['unmatched_user'] = true;
            $this->logStageWarning('PayPal webhook event recorded without a matching user', [
                'user_found' => false,
                'resource_id' => $this->resourceId,
            ]);

            return;
        }

        $this->result['user_found'] = true;

        $status = match ($this->eventType) {
            'BILLING.SUBSCRIPTION.ACTIVATED',
            'BILLING.SUBSCRIPTION.RE-ACTIVATED' => 'active',
            'BILLING.SUBSCRIPTION.CANCELLED' => 'cancelled',
            'BILLING.SUBSCRIPTION.SUSPENDED' => 'suspended',
            'BILLING.SUBSCRIPTION.EXPIRED' => 'expired',
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => 'payment_failed',
            default => null,
        };

        if (! $status) {
            $this->result['unknown_event'] = true;
            $this->result['subscription_status'] = null;

            $this->logStageWarning('PayPal webhook event type is currently unhandled by business processing', [
                'event_type' => $this->eventType,
                'resource_id' => $this->resourceId,
            ]);

            return;
        }

        if ($this->user->billing_provider !== 'paypal') {
            $this->result['ownership_guarded'] = true;
            $this->logStageInfo('PayPal webhook business processing skipped because the user is not a PayPal billing provider', [
                'user_id' => $this->user->id,
                'billing_provider' => $this->user->billing_provider,
            ]);

            return;
        }

        $updates = [
            'paypal_subscription_status' => $status,
        ];

        if ($status === 'active') {
            $updates['media_storage_tier'] = 'dj_plus';
        } elseif ($status !== 'payment_failed') {
            $updates['media_storage_tier'] = 'free';
        }

        $this->user->forceFill($updates)->save();

        $this->result['user_updated'] = true;
        $this->result['subscription_status'] = $status;

        if ($status === 'active') {
            $this->referralQualification->qualifySubscription(
                user: $this->user,
                provider: 'paypal',
                transactionId: $this->resourceId,
                source: 'paypal_webhook:'.$this->eventType,
                planKey: 'dj_plus',
                status: $status,
            );

            $this->result['affiliate_qualified'] = true;
        }
    }

    protected function markWebhookEventProcessed(): void
    {
        if (! $this->webhookEvent) {
            throw new RuntimeException(
                'Cannot mark PayPal webhook processed before it is recorded.'
            );
        }

        $updated = PayPalWebhookEvent::query()
            ->whereKey($this->webhookEvent->getKey())
            ->update([
                'processed_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException(
                'PayPal webhook processed timestamp could not be updated.'
            );
        }
    }

    protected function paymentLogChannel(): string
    {
        $channels = [
            'initialize' => 'payments_initialize',
            'receive' => 'payments_receive',
            'validate' => 'payments_validate',
            'protect' => 'payments_protect',
            'record' => 'payments_record',
            'process' => 'payments_process',
            'respond' => 'payments_respond',
        ];

        return $channels[$this->stage] ?? 'payments_system';
    }

    protected function logStageInfo(string $message, array $context = []): void
    {
        $this->writePaymentLog('info', $message, $context);
    }

    protected function logStageWarning(string $message, array $context = []): void
    {
        $this->writePaymentLog('warning', $message, $context);
    }

    protected function logStageError(\Throwable $exception, array $additionalContext = []): void
    {
        $context = [
            'stage' => $this->stage,
            'status' => $this->status,
            'provider' => $this->provider,
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'resource_id' => $this->resourceId,
            'transmission_id' => $this->transmissionId,
            'webhook_event_id' => $this->webhookEvent?->id,
            'user_id' => $this->user?->id,
            'processed_at' => $this->webhookEvent?->processed_at,
            'recorded_unprocessed' => $this->webhookEvent ? $this->webhookEvent->processed_at === null : false,
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'source_file' => $exception->getFile(),
            'source_line' => $exception->getLine(),
            ...$additionalContext,
        ];

        $this->writePaymentLog('error', $exception->getMessage(), $context);
    }

    protected function writePaymentLog(string $level, string $message, array $context = []): void
    {
        $channel = $this->paymentLogChannel();

        try {
            $logger = Log::channel($channel);
            $logger->{$level}($message, [
                'stage' => $this->stage,
                'status' => $this->status,
                'provider' => $this->provider,
                'event_id' => $this->eventId,
                'event_type' => $this->eventType,
                'resource_id' => $this->resourceId,
                'transmission_id' => $this->transmissionId,
                'webhook_event_id' => $this->webhookEvent?->id,
                'user_id' => $this->user?->id,
                'recovery_mode' => $this->recoveryMode,
                ...$context,
            ]);
        } catch (\Throwable $loggingException) {
            try {
                Log::error('Payment stage logging failed.', [
                    'stage' => $this->stage,
                    'fallback_channel' => $channel,
                    'original_exception_class' => $context['exception_class'] ?? 'unknown',
                    'logging_exception_class' => get_class($loggingException),
                ]);
            } catch (\Throwable) {
                // Intentionally silent to preserve the original workflow exception.
            }
        }
    }

    protected function canProcessEvent(): bool
    {
        if (! $this->eventType) {
            return true;
        }

        if (! $this->user) {
            return true;
        }

        if ($this->user->billing_provider !== 'paypal') {
            return true;
        }

        switch ($this->eventType) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
            case 'BILLING.SUBSCRIPTION.RE-ACTIVATED':
            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                // At Stage 5 we observe existing subscription lifecycle events
                // and allow them unless a clearly unsafe transition is already supported by existing behavior.
                return true;
            default:
                return true;
        }
    }

    protected function getHeaderReference(): array
    {
        $path = base_path('docs/payments/paypal.php');

        if (! is_file($path)) {
            throw new RuntimeException(
                'PayPal header reference file is missing.'
            );
        }

        $reference = require $path;

        if (! is_array($reference)) {
            throw new RuntimeException(
                'PayPal header reference must return an array.'
            );
        }

        if (! isset($reference['required_headers']) || ! is_array($reference['required_headers'])) {
            throw new RuntimeException(
                'PayPal header reference must contain a required_headers array.'
            );
        }

        return $reference;
    }

    protected function shouldEnforceWebhookSignature(): bool
    {
        return config('billing.paypal.enforce_signature', false);
    }

    protected function shouldEnforceDuplicateProtection(): bool
    {
        return config('billing.paypal.enforce_duplicates', false);
    }

    protected function shouldEnforceReplayProtection(): bool
    {
        return config('billing.paypal.enforce_replay_protection', false);
    }

    protected function shouldEnforceProcessingLock(): bool
    {
        return config('billing.paypal.enforce_processing_lock', false);
    }

    protected function processingLockKey(): string
    {
        $identifier = $this->resourceId ?: $this->eventId ?: 'unknown';
        $safeIdentifier = preg_replace('/[^A-Za-z0-9_-]/', '_', $identifier);

        return 'paypal_webhook_processing:'.$safeIdentifier;
    }

    protected function acquireProcessingLock(): bool
    {
        if (! $this->resourceId && ! $this->eventId) {
            return true;
        }

        $this->processingLockKey = $this->processingLockKey();

        try {
            $lock = Cache::lock($this->processingLockKey, 30);
            $acquired = $lock->get();
        } catch (\Throwable $exception) {
            $this->logStageInfo('Unable to initialize PayPal webhook processing lock', [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $acquired) {
            return false;
        }

        $this->processingLock = $lock;
        $this->processingLockAcquired = true;

        $this->logStageInfo('PayPal webhook processing lock acquired', [
            'processing_lock_key' => $this->processingLockKey,
        ]);

        return true;
    }

    protected function releaseProcessingLock(): void
    {
        if (! $this->processingLockAcquired || ! $this->processingLock) {
            return;
        }

        try {
            $this->processingLock->release();
            $this->logStageInfo('PayPal webhook processing lock released', [
                'processing_lock_key' => $this->processingLockKey,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to release PayPal webhook processing lock', [
                'processing_lock_key' => $this->processingLockKey,
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ]);
        } finally {
            $this->processingLock = null;
            $this->processingLockAcquired = false;
            $this->processingLockKey = null;
        }
    }

    protected function verifyWebhookSignatureWithPayPal(): bool
    {
        $transmissionId = $this->request->header('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $this->request->header('PAYPAL-TRANSMISSION-TIME');
        $transmissionSig = $this->request->header('PAYPAL-TRANSMISSION-SIG');
        $certUrl = $this->request->header('PAYPAL-CERT-URL');
        $authAlgo = $this->request->header('PAYPAL-AUTH-ALGO');

        if (! is_string($transmissionId) || $transmissionId === '') {
            throw new RuntimeException('PayPal webhook transmission ID header is invalid.');
        }

        if (! is_string($transmissionTime) || $transmissionTime === '') {
            throw new RuntimeException('PayPal webhook transmission time header is invalid.');
        }

        if (! is_string($transmissionSig) || $transmissionSig === '') {
            throw new RuntimeException('PayPal webhook transmission signature header is invalid.');
        }

        if (! is_string($certUrl) || $certUrl === '') {
            throw new RuntimeException('PayPal webhook certificate URL header is invalid.');
        }

        if (! is_string($authAlgo) || $authAlgo === '') {
            throw new RuntimeException('PayPal webhook auth algorithm header is invalid.');
        }

        $webhookEvent = json_decode($this->rawPayload, true);

        if (! is_array($webhookEvent)) {
            throw new RuntimeException(
                'PayPal webhook raw payload could not be decoded as JSON for signature verification.'
            );
        }

        try {
            $accessToken = $this->paypalAccessToken();

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($this->paypalBaseUrl().'/v1/notifications/verify-webhook-signature', [
                    'transmission_id' => $transmissionId,
                    'transmission_time' => $transmissionTime,
                    'cert_url' => $certUrl,
                    'auth_algo' => $authAlgo,
                    'transmission_sig' => $transmissionSig,
                    'webhook_id' => $this->webhookId,
                    'webhook_event' => $webhookEvent,
                ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'PayPal webhook signature verification request failed.',
                0,
                $exception
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'PayPal webhook signature verification endpoint returned an error.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['verification_status'])) {
            throw new RuntimeException(
                'PayPal webhook verification response was malformed.'
            );
        }

        return $payload['verification_status'] === 'SUCCESS';
    }

    protected function paypalAccessToken(): string
    {
        if (! $this->paymentProvider) {
            throw new RuntimeException(
                'PayPal provider configuration is required to obtain an access token.'
            );
        }

        $clientId = $this->paypalClientId;
        $secret = $this->paypalSecret;

        if (! $clientId || ! $secret) {
            throw new RuntimeException(
                'PayPal client credentials are missing for webhook verification.'
            );
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->post($this->paypalBaseUrl().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'PayPal access token request failed.',
                0,
                $exception
            );
        }

        if ($response->failed()) {
            throw new RuntimeException(
                'PayPal access token request returned an error.'
            );
        }

        $body = $response->json();

        if (! is_array($body) || ! isset($body['access_token'])) {
            throw new RuntimeException(
                'PayPal access token response was malformed.'
            );
        }

        return (string) $body['access_token'];
    }

    protected function paypalBaseUrl(): string
    {
        return match ($this->paypalMode) {
            'sandbox' => 'https://api-m.sandbox.paypal.com',
            'live' => 'https://api-m.paypal.com',
            default => throw new RuntimeException(
                'PayPal mode must be resolved before selecting an API endpoint.'
            ),
        };
    }

    protected function replayCheck(): array
    {
        $result = [
            'detected' => false,
            'reason' => null,
            'age_seconds' => null,
            'enforceable' => false,
        ];

        if (! $this->transmissionTime) {
            $result['reason'] = 'missing_timestamp';
            $result['enforceable'] = false;
            return $result;
        }

        try {
            $sentAt = Carbon::parse($this->transmissionTime);
        } catch (\Throwable $exception) {
            $this->logStageWarning('Unable to parse PayPal transmission time', [
                'transmission_time' => $this->transmissionTime,
                'replay_check_mode' => $this->shouldEnforceReplayProtection()
                    ? 'enforcing'
                    : 'diagnostic_only',
            ]);

            $result['reason'] = 'invalid_timestamp';
            $result['enforceable'] = false;
            return $result;
        }

        $now = Carbon::now();
        $ageSeconds = $sentAt->diffInSeconds($now, false);

        $result['age_seconds'] = $ageSeconds;

        if ($ageSeconds > $this->replayWindowSeconds) {
            $result['detected'] = true;
            $result['reason'] = 'expired';
            $result['enforceable'] = $this->shouldEnforceReplayProtection();
            return $result;
        }

        if ($ageSeconds < -$this->replayWindowSeconds) {
            $result['detected'] = true;
            $result['reason'] = 'future_timestamp';
            $result['enforceable'] = $this->shouldEnforceReplayProtection();
            return $result;
        }

        $result['reason'] = 'valid';
        $result['enforceable'] = false;
        return $result;
    }
}
