<?php

namespace App\Services;

use App\Models\EventLog;

class EventTimelineService
{
    public function record(
        int $tenantId,
        string $eventType,
        string $entityType,
        ?int $entityId = null,
        array $payload = []
    ): EventLog {
        return EventLog::query()->create([
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload_json' => $payload,
            'occurred_at' => now(),
        ]);
    }
}

