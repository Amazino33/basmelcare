<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToBranch;

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
