<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use BelongsToBranch;
    use NormalisesName;

    protected $fillable = ['name', 'type', 'address', 'is_default', 'branch_id'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
