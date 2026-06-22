<?php

namespace App\Models\Traits;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            if (!$model->branch_id && auth()->check() && auth()->user()->branch_id) {
                $model->branch_id = auth()->user()->branch_id;
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
