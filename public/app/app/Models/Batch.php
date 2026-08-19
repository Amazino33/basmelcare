<?php

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use RecordsAudit;

    /** Cost and quantity together set stock value and reported profit. */
    protected array $audited = ['cost_price', 'quantity'];
    protected string $auditLabel = 'batch_number';

    protected $fillable = [
        'product_id', 'location_id', 'batch_number', 'expiry_date', 'cost_price', 'quantity', 'note',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'cost_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
