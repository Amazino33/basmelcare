<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use NormalisesName;

    protected $fillable = [
        'name', 'description', 'image',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
