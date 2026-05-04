<?php

namespace App\Support;

use App\Models\Audit;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    public static function forModelEvent(string $event, Model $model, array $oldValues = [], array $newValues = [], array $meta = []): Audit
    {
        return self::write(
            event: $event,
            auditableType: $model::class,
            auditableId: (int) $model->getKey(),
            oldValues: self::sanitize($oldValues),
            newValues: self::sanitize(array_merge($newValues, $meta))
        );
    }

    public static function authEvent(string $event, ?Model $subject = null, array $meta = []): Audit
    {
        $subject ??= Auth::user();
        $subjectClass = $subject ? $subject::class : \App\Models\User::class;

        return self::write(
            event: $event,
            auditableType: $subjectClass,
            auditableId: (int) ($subject?->getKey() ?? 0),
            oldValues: [],
            newValues: self::sanitize($meta)
        );
    }

    public static function requestEvent(string $event, ?Model $subject = null, array $meta = []): Audit
    {
        $subject ??= Auth::user();
        $subjectClass = $subject ? $subject::class : \App\Models\User::class;

        return self::write(
            event: $event,
            auditableType: $subjectClass,
            auditableId: (int) ($subject?->getKey() ?? 0),
            oldValues: [],
            newValues: self::sanitize($meta)
        );
    }

    public static function write(
        string $event,
        string $auditableType,
        int $auditableId,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null
    ): Audit {
        $request ??= request();
        $user = Auth::user();

        return Audit::create([
            'tenant_id' => app(TenantContext::class)->tenantId() ?? $user?->tenant_id,
            'user_type' => $user ? $user::class : null,
            'user_id' => $user?->getKey(),
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 1023, ''),
            'tags' => self::buildTags($request, $event),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (in_array((string) $key, ['password', 'password_confirmation', 'remember_token', 'external_password_hash'], true)) {
                continue;
            }

            $sanitized[$key] = self::normalize($value);
        }

        return $sanitized;
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Model) {
            return [
                'type' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item) => self::normalize($item), $value);
        }

        if (is_object($value)) {
            return method_exists($value, 'toArray')
                ? self::normalize($value->toArray())
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }

    private static function buildTags(?Request $request, string $event): ?string
    {
        if (! $request) {
            return $event;
        }

        $parts = array_filter([
            $event,
            $request->route()?->getName(),
            strtolower($request->method()),
        ]);

        return implode(',', $parts);
    }
}
