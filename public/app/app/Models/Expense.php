<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToBranch;
    use RecordsAudit;

    /**
     * An expense is money leaving the till, and cashiers can now record and
     * correct one. Every field on it moves money, so every field is audited -
     * a figure quietly edited afterwards would otherwise leave no trace.
     */
    protected array $audited = ['category', 'description', 'amount', 'expense_date'];
    protected string $auditLabel = 'description';

    protected $fillable = ['branch_id', 'user_id', 'category', 'description', 'amount', 'expense_date'];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function categories(): array
    {
        return [
            'rent'        => 'Rent',
            'utilities'   => 'Utilities',
            'salary'      => 'Salary',
            'procurement' => 'Procurement',
            'maintenance' => 'Maintenance',
            'petty_cash'  => 'Petty Cash',
            'other'       => 'Other',
        ];
    }
}
