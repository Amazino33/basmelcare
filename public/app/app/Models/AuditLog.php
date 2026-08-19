<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'auditable_type', 'auditable_id', 'auditable_label',
        'event', 'field', 'old_value', 'new_value',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Product", "Batch", "Coupon", "Setting" — for display. */
    public function typeLabel(): string
    {
        return match (class_basename($this->auditable_type)) {
            'AppSetting' => 'Setting',
            default      => class_basename($this->auditable_type),
        };
    }

    /** Human-readable field name. */
    public function fieldLabel(): string
    {
        return $this->field
            ? ucfirst(str_replace('_', ' ', $this->field))
            : '';
    }
}
