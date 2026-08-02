<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Product;

class InventoryPrintController extends Controller
{
    public function __invoke()
    {
        $products = Product::with(['category', 'batches' => fn($q) => $q->where('quantity', '>', 0)->orderBy('expiry_date')])
            ->orderBy('name')
            ->get();

        return view('inventory.print', [
            'products'      => $products,
            'pharmacyName'  => AppSetting::get('pharmacy_name', 'BasmelCare Pharmacy'),
            'printedAt'     => now()->format('d M Y, g:i A'),
        ]);
    }
}
