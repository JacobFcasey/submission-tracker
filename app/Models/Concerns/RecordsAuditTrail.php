<?php

namespace App\Models\Concerns;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

trait RecordsAuditTrail
{
    public static function bootRecordsAuditTrail(): void
    {
        static::created(function (Model $model): void {
            AuditLogger::forModelEvent('created', $model, [], $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $oldValues = [];

            foreach (array_keys($changes) as $key) {
                $oldValues[$key] = $model->getOriginal($key);
            }

            AuditLogger::forModelEvent('updated', $model, $oldValues, $changes);
        });

        static::deleted(function (Model $model): void {
            AuditLogger::forModelEvent('deleted', $model, $model->getOriginal(), []);
        });
    }
}
