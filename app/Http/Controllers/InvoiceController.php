<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Sale;

class InvoiceController extends Controller
{
    public function show(Sale $sale)
    {
        $sale->load('saleItems.product', 'saleItems.batch', 'user', 'customer');

        return view('invoices.show', $this->pharmacyData($sale));
    }

    public function receipt(Sale $sale)
    {
        $sale->load('saleItems.product', 'user', 'customer');

        return view('receipts.show', $this->pharmacyData($sale));
    }

    private function pharmacyData(Sale $sale): array
    {
        return [
            'sale' => $sale,
            'pharmacyName' => AppSetting::get('pharmacy_name', ''),
            'pharmacyPhone' => AppSetting::get('pharmacy_phone', ''),
            'pharmacyEmail' => AppSetting::get('pharmacy_email', ''),
            'pharmacyAddress' => AppSetting::get('pharmacy_address', ''),
            'pharmacyWebsite' => AppSetting::get('pharmacy_website', ''),
        ];
    }
}
