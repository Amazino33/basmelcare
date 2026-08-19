<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Records who changed the values that drive money — selling prices, batch
 * costs, coupons and settings — with before/after values.
 *
 * Hooked into Eloquent events rather than call sites, so a new screen that
 * edits a price is audited automatically instead of being silently missed.
 *
 * A model opts in with:
 *     use RecordsAudit;
 *     protected array $audited = ['selling_price'];   // [] = every fillable field
 *     protected string $auditLabel = 'name';          // column used to name the row
 */
trait RecordsAudit
{
    public static function bootRecordsAudit(): void
    {
        static::created(fn ($model) => $model->writeAuditEntry('created'));
        static::deleted(fn ($model) => $model->writeAuditEntry('deleted'));

        static::updated(function ($model) {
            foreach ($model->auditedFields() as $field) {
                if (! $model->wasChanged($field)) {
                    continue;
                }

                $model->writeAuditEntry(
                    'updated',
                    $field,
                    $model->getOriginal($field),
                    $model->getAttribute($field)
                );
            }
        });
    }

    public function auditedFields(): array
    {
        return property_exists($this, 'audited') && $this->audited !== []
            ? $this->audited
            : $this->getFillable();
    }

    public function auditLabel(): ?string
    {
        $column = property_exists($this, 'auditLabel') ? $this->auditLabel : 'name';

        return $this->getAttribute($column) !== null
            ? (string) $this->getAttribute($column)
            : null;
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected function writeAuditEntry(
        string $event,
        ?string $field = null,
        mixed $old = null,
        mixed $new = null
    ): void {
        AuditLog::create([
            // Null when a change comes from a console command or seeder.
            'user_id'         => auth()->id(),
            'auditable_type'  => static::class,
            'auditable_id'    => (string) $this->getKey(),
            'auditable_label' => $this->auditLabel(),
            'event'           => $event,
            'field'           => $field,
            'old_value'       => $old === null ? null : (string) $old,
            'new_value'       => $new === null ? null : (string) $new,
            'created_at'      => now(),
        ]);
    }
}
