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
            if ($model->branch_id) {
                return;
            }

            // The user's own branch, where they have one.
            if (auth()->check() && auth()->user()->branch_id) {
                $model->branch_id = auth()->user()->branch_id;

                return;
            }

            // Otherwise the main branch, rather than nothing at all. A record
            // saved with no branch is hidden from everyone who has one and
            // shown to everyone who does not - so the same expense appears for
            // one cashier and not the other, with nothing to explain why.
            $model->branch_id = Branch::where('is_main', true)->value('id')
                ?? Branch::orderBy('id')->value('id');
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
