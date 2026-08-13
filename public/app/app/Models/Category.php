<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name', 'description',
    ];

    public function getNameAttribute(string $value): string
    {
        return strtoupper($value);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
