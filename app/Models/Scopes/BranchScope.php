<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (!$user) return;

        // Admin sees everything
        if ($user->hasRole('admin')) return;

        // Users with a branch only see their branch data
        if ($user->branch_id) {
            $builder->where($model->getTable() . '.branch_id', $user->branch_id);
        }
    }
}
