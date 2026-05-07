<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CapsWebhookEvent;
use App\Models\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Layer 3 – Status Echo-back from CAPS.
 *
 * Receives webhook events published by CAPS when a payment batch changes
 * state (imported, allocated, failed, refund created). Each event carries
 * a `paymentsBatchId` and/or `submissionReference` which we use to locate
 * the matching Upload row. The row's `caps_status` column is updated so the
 * Tracker UI reflects real CAPS processing status without polling.
 *
 * Security: every request must carry an `X-Caps-Signature` header containing
 * the HMAC-SHA256 of the raw request body using the shared secret. Requests
 * with an invalid or missing signature are rejected with 401.
 *
 * Idempotency: each event carries a unique `eventId`. Replayed events are
 * detected via the `caps_webhook_events` table and acknowledged with 200
 * without re-processing.
 */
class CapsWebhookController extends Controller
{
    /**
     * Accepted event types and the caps_status value they map to.
     */
    private const EVENT_STATUS_MAP = [
        'payment_batch.imported'  => 'imported',
        'payment_batch.allocated' => 'allocated',
        'payment_batch.failed'    => 'failed',
        'payment_batch.exported'  => 'exported',
        'refund.created'          => 'refund_created',
        'refund.allocated'        => 'refund_allocated',
    ];

    public function handle(Request $request): JsonResponse
    {
        // --- Signature verification ---
        $secret = (string) config('services.casey.webhook_secret', '');
        if ($secret === '') {
            Log::error('CAPS webhook received but CAPS_WEBHOOK_SECRET is not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 500);
        }

        $signature = (string) $request->header('X-Caps-Signature', '');
        $rawBody = (string) $request->getContent();
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('CAPS webhook rejected: invalid signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // --- Parse payload ---
        $payload = $request->json()->all();
        $eventId = (string) ($payload['eventId'] ?? $payload['event_id'] ?? '');
        $eventType = (string) ($payload['event'] ?? $payload['eventType'] ?? $payload['event_type'] ?? '');
        $batchId = (string) ($payload['paymentsBatchId'] ?? $payload['payments_batch_id'] ?? '');
        $reference = (string) ($payload['submissionReference'] ?? $payload['submission_reference'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $errors = $payload['errors'] ?? [];
        $occurredAt = $payload['occurredAt'] ?? $payload['occurred_at'] ?? now()->toIso8601String();

        if ($eventId === '') {
            $eventId = md5($eventType . '|' . $batchId . '|' . $reference . '|' . $occurredAt);
        }

        if ($eventType === '') {
            return response()->json(['error' => 'Missing event type'], 422);
        }

        // --- Idempotency ---
        $existing = CapsWebhookEvent::where('event_id', $eventId)->first();
        if ($existing) {
            return response()->json([
                'ok' => true,
                'message' => 'Event already processed',
                'event_id' => $eventId,
            ]);
        }

        // --- Resolve the matching Upload ---
        $upload = $this->resolveUpload($batchId, $reference);

        // --- Map event type to a caps_status value ---
        $capsStatus = self::EVENT_STATUS_MAP[$eventType] ?? $eventType;
        $detail = null;
        if (is_array($errors) && count($errors) > 0) {
            $detail = implode("\n", array_map(fn ($e) => is_string($e) ? $e : json_encode($e), $errors));
        } elseif (! empty($payload['message'])) {
            $detail = (string) $payload['message'];
        }

        // --- Persist event ---
        $event = CapsWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payments_batch_id' => $batchId ?: null,
            'submission_reference' => $reference ?: null,
            'status' => $capsStatus,
            'payload' => $payload,
            'upload_id' => $upload?->id,
        ]);

        // --- Update the Upload if we found one ---
        if ($upload) {
            $updateData = [
                'caps_payment_batch_id' => $batchId ?: $upload->caps_payment_batch_id,
                'caps_status' => $capsStatus,
                'caps_status_detail' => $detail ?? $upload->caps_status_detail,
                'caps_last_webhook_at' => Carbon::parse($occurredAt),
            ];

            // Map CAPS webhook status to local dispatch workflow status
            if (in_array($capsStatus, ['allocated', 'exported', 'refund_allocated'], true)) {
                $updateData['caps_dispatch_status'] = Uploads::DISPATCH_COMPLETED;
            } elseif ($capsStatus === 'failed') {
                $updateData['caps_dispatch_status'] = Uploads::DISPATCH_FAILED;
                $updateData['caps_errors'] = is_array($errors) && count($errors) > 0 ? $errors : $upload->caps_errors;
            } elseif (in_array($capsStatus, ['imported', 'refund_created'], true)) {
                $updateData['caps_dispatch_status'] = Uploads::DISPATCH_CAPS_PROCESSING;
            }

            // Store CAPS summary data if available
            if (!empty($payload['summary']) || !empty($payload['totalRows'])) {
                $updateData['caps_summary'] = [
                    'total_rows' => $payload['totalRows'] ?? $payload['summary']['total'] ?? null,
                    'processed' => $payload['processedRows'] ?? $payload['summary']['processed'] ?? null,
                    'errors' => $payload['errorRows'] ?? $payload['summary']['errors'] ?? null,
                ];
            }

            $upload->update($updateData);

            Log::info('CAPS webhook updated upload', [
                'upload_id' => $upload->id,
                'event_type' => $eventType,
                'caps_status' => $capsStatus,
                'batch_id' => $batchId,
            ]);
        } else {
            Log::warning('CAPS webhook received but no matching upload found', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'batch_id' => $batchId,
                'reference' => $reference,
            ]);
        }

        return response()->json([
            'ok' => true,
            'event_id' => $eventId,
            'upload_id' => $upload?->id,
            'caps_status' => $capsStatus,
        ]);
    }

    /**
     * Try to find the Upload that this event belongs to:
     *   1. By caps_payment_batch_id (exact match if batch was linked before)
     *   2. By reference (the submission reference carried through)
     */
    private function resolveUpload(string $batchId, string $reference): ?Uploads
    {
        if ($batchId !== '') {
            $upload = Uploads::where('caps_payment_batch_id', $batchId)->first();
            if ($upload) {
                return $upload;
            }
        }

        if ($reference !== '') {
            return Uploads::where('reference', $reference)->first();
        }

        return null;
    }
}
