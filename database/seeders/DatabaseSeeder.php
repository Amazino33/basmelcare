<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Appointment;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Debt;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Branches
        $mainBranch = Branch::firstOrCreate(['name' => 'Main Branch'], [
            'address' => '15 Health Avenue, Victoria Island, Lagos',
            'phone' => '08012345678',
            'is_main' => true,
        ]);

        $ikejaBranch = Branch::firstOrCreate(['name' => 'Ikeja Branch'], [
            'address' => '42 Allen Avenue, Ikeja, Lagos',
            'phone' => '08099887766',
            'is_main' => false,
        ]);

        // Locations
        $shop = Location::firstOrCreate(['name' => 'Main Shop'], ['type' => 'shop', 'is_default' => true, 'branch_id' => $mainBranch->id]);
        $warehouse = Location::firstOrCreate(['name' => 'Warehouse'], ['type' => 'warehouse', 'is_default' => false, 'branch_id' => $mainBranch->id]);
        Location::firstOrCreate(['name' => 'Ikeja Shop'], ['type' => 'shop', 'is_default' => false, 'branch_id' => $ikejaBranch->id]);

        // Staff
        $admin = User::firstOrCreate(['email' => 'admin@basmelcare.com'], [
            'name' => 'Dr. Basmel',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'position' => 'Owner / Head Pharmacist',
            'employment_date' => '2024-01-01',
            'salary' => 500000,
            'phone' => '08012345678',
            'status' => 'active',
            'branch_id' => null,
        ]);

        $pharmacist = User::firstOrCreate(['email' => 'pharm@basmelcare.com'], [
            'name' => 'Adunni Pharmacist',
            'password' => bcrypt('password'),
            'role' => 'pharmacist',
            'position' => 'Senior Pharmacist',
            'employment_date' => '2024-06-15',
            'salary' => 350000,
            'phone' => '08023456789',
            'status' => 'active',
            'branch_id' => $mainBranch->id,
        ]);

        $cashier = User::firstOrCreate(['email' => 'cashier@basmelcare.com'], [
            'name' => 'Bola Cashier',
            'password' => bcrypt('password'),
            'role' => 'cashier',
            'position' => 'Cashier',
            'employment_date' => '2025-01-10',
            'salary' => 150000,
            'phone' => '08034567890',
            'status' => 'active',
            'branch_id' => $mainBranch->id,
        ]);

        $branchMgr = User::firstOrCreate(['email' => 'branch@basmelcare.com'], [
            'name' => 'Emeka Branch Manager',
            'password' => bcrypt('password'),
            'role' => 'branch_manager',
            'position' => 'Ikeja Branch Manager',
            'employment_date' => '2025-02-01',
            'salary' => 280000,
            'phone' => '08056789012',
            'status' => 'active',
            'branch_id' => $ikejaBranch->id,
        ]);

        $invManager = User::firstOrCreate(['email' => 'inventory@basmelcare.com'], [
            'name' => 'Chidi Inventory',
            'password' => bcrypt('password'),
            'role' => 'inventory_manager',
            'position' => 'Stock Manager',
            'employment_date' => '2025-03-01',
            'salary' => 180000,
            'phone' => '08045678901',
            'status' => 'active',
            'branch_id' => $mainBranch->id,
        ]);

        // Categories
        $categories = [];
        foreach ([
            'Antibiotics' => 'Medications that fight bacterial infections',
            'Painkillers' => 'Pain relief medications',
            'Vitamins & Supplements' => 'Nutritional supplements and vitamins',
            'Cough & Cold' => 'Cold, flu, and cough medicines',
            'Antimalaria' => 'Malaria prevention and treatment',
            'First Aid' => 'Bandages, antiseptics, and wound care',
            'Skincare' => 'Dermatological products',
            'Baby Care' => 'Infant health products',
            'Diabetes' => 'Diabetes management products',
            'Blood Pressure' => 'Hypertension medications',
        ] as $name => $desc) {
            $categories[$name] = Category::firstOrCreate(['name' => $name], ['description' => $desc]);
        }

        // Suppliers
        $suppliers = [];
        foreach ([
            ['name' => 'PharmaCo Nigeria', 'phone' => '08055551234', 'email' => 'orders@pharmaco.ng', 'contact_person' => 'Mr. Okonkwo'],
            ['name' => 'MedSupply Ltd', 'phone' => '08055555678', 'email' => 'sales@medsupply.com', 'contact_person' => 'Mrs. Adeyemi'],
            ['name' => 'HealthDist West Africa', 'phone' => '08055559012', 'email' => 'info@healthdist.com', 'contact_person' => 'Dr. Obi'],
        ] as $data) {
            $suppliers[] = Supplier::firstOrCreate(['name' => $data['name']], $data);
        }

        // Products with batches
        $productsData = [
            ['name' => 'Amoxicillin 500mg', 'category' => 'Antibiotics', 'selling_price' => 1500, 'wholesale_price' => 1200, 'wholesale_min_qty' => 20, 'reorder_level' => 50, 'batches' => [
                ['bn' => 'AMX-2026-001', 'cost' => 800, 'qty' => 200, 'expiry' => now()->addMonths(18), 'loc' => $shop],
                ['bn' => 'AMX-2026-002', 'cost' => 850, 'qty' => 100, 'expiry' => now()->addMonths(12), 'loc' => $warehouse],
            ]],
            ['name' => 'Ciprofloxacin 500mg', 'category' => 'Antibiotics', 'selling_price' => 2000, 'wholesale_price' => 1600, 'wholesale_min_qty' => 15, 'reorder_level' => 30, 'batches' => [
                ['bn' => 'CIP-2026-001', 'cost' => 1100, 'qty' => 150, 'expiry' => now()->addMonths(14), 'loc' => $shop],
            ]],
            ['name' => 'Paracetamol 500mg', 'category' => 'Painkillers', 'selling_price' => 200, 'wholesale_price' => 150, 'wholesale_min_qty' => 50, 'reorder_level' => 100, 'batches' => [
                ['bn' => 'PCM-2026-001', 'cost' => 80, 'qty' => 500, 'expiry' => now()->addMonths(24), 'loc' => $shop],
                ['bn' => 'PCM-2026-002', 'cost' => 85, 'qty' => 300, 'expiry' => now()->addMonths(20), 'loc' => $warehouse],
            ]],
            ['name' => 'Ibuprofen 400mg', 'category' => 'Painkillers', 'selling_price' => 350, 'wholesale_price' => 280, 'wholesale_min_qty' => 30, 'reorder_level' => 60, 'batches' => [
                ['bn' => 'IBU-2026-001', 'cost' => 150, 'qty' => 300, 'expiry' => now()->addMonths(16), 'loc' => $shop],
            ]],
            ['name' => 'Vitamin C 1000mg', 'category' => 'Vitamins & Supplements', 'selling_price' => 1200, 'wholesale_price' => 950, 'wholesale_min_qty' => 10, 'reorder_level' => 20, 'batches' => [
                ['bn' => 'VTC-2026-001', 'cost' => 600, 'qty' => 80, 'expiry' => now()->addMonths(22), 'loc' => $shop],
            ]],
            ['name' => 'Multivitamin Tablets', 'category' => 'Vitamins & Supplements', 'selling_price' => 2500, 'wholesale_price' => 2000, 'wholesale_min_qty' => 10, 'reorder_level' => 15, 'batches' => [
                ['bn' => 'MVT-2026-001', 'cost' => 1400, 'qty' => 50, 'expiry' => now()->addMonths(20), 'loc' => $shop],
            ]],
            ['name' => 'Cough Syrup (Benylin)', 'category' => 'Cough & Cold', 'selling_price' => 1800, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 20, 'batches' => [
                ['bn' => 'BEN-2026-001', 'cost' => 950, 'qty' => 40, 'expiry' => now()->addMonths(10), 'loc' => $shop],
            ]],
            ['name' => 'Cold Cap (Procold)', 'category' => 'Cough & Cold', 'selling_price' => 300, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 50, 'batches' => [
                ['bn' => 'PRC-2026-001', 'cost' => 120, 'qty' => 200, 'expiry' => now()->addMonths(15), 'loc' => $shop],
            ]],
            ['name' => 'Artemether/Lumefantrine (Coartem)', 'category' => 'Antimalaria', 'selling_price' => 2500, 'wholesale_price' => 2000, 'wholesale_min_qty' => 10, 'reorder_level' => 25, 'batches' => [
                ['bn' => 'COA-2026-001', 'cost' => 1300, 'qty' => 60, 'expiry' => now()->addMonths(12), 'loc' => $shop],
                ['bn' => 'COA-2025-003', 'cost' => 1200, 'qty' => 15, 'expiry' => now()->addDays(45), 'loc' => $shop],
            ]],
            ['name' => 'Chloroquine Tablets', 'category' => 'Antimalaria', 'selling_price' => 500, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 30, 'batches' => [
                ['bn' => 'CLQ-2025-001', 'cost' => 200, 'qty' => 20, 'expiry' => now()->addDays(25), 'loc' => $shop],
            ]],
            ['name' => 'Antiseptic Solution (Dettol)', 'category' => 'First Aid', 'selling_price' => 1500, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 10, 'batches' => [
                ['bn' => 'DET-2026-001', 'cost' => 800, 'qty' => 30, 'expiry' => now()->addMonths(30), 'loc' => $shop],
            ]],
            ['name' => 'Adhesive Bandage (Pack)', 'category' => 'First Aid', 'selling_price' => 500, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 15, 'batches' => [
                ['bn' => 'BND-2026-001', 'cost' => 200, 'qty' => 50, 'expiry' => now()->addMonths(36), 'loc' => $shop],
            ]],
            ['name' => 'Calamine Lotion', 'category' => 'Skincare', 'selling_price' => 800, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 10, 'batches' => [
                ['bn' => 'CAL-2026-001', 'cost' => 400, 'qty' => 25, 'expiry' => now()->addMonths(18), 'loc' => $shop],
            ]],
            ['name' => 'Baby Gripe Water', 'category' => 'Baby Care', 'selling_price' => 600, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 15, 'batches' => [
                ['bn' => 'GRP-2026-001', 'cost' => 300, 'qty' => 40, 'expiry' => now()->addMonths(12), 'loc' => $shop],
            ]],
            ['name' => 'Metformin 500mg', 'category' => 'Diabetes', 'selling_price' => 1800, 'wholesale_price' => 1400, 'wholesale_min_qty' => 10, 'reorder_level' => 20, 'batches' => [
                ['bn' => 'MET-2026-001', 'cost' => 900, 'qty' => 70, 'expiry' => now()->addMonths(16), 'loc' => $shop],
            ]],
            ['name' => 'Insulin Glargine (Pen)', 'category' => 'Diabetes', 'selling_price' => 12000, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 5, 'batches' => [
                ['bn' => 'INS-2026-001', 'cost' => 8000, 'qty' => 10, 'expiry' => now()->addMonths(6), 'loc' => $shop],
            ]],
            ['name' => 'Amlodipine 5mg', 'category' => 'Blood Pressure', 'selling_price' => 1500, 'wholesale_price' => 1200, 'wholesale_min_qty' => 10, 'reorder_level' => 25, 'batches' => [
                ['bn' => 'AML-2026-001', 'cost' => 700, 'qty' => 90, 'expiry' => now()->addMonths(20), 'loc' => $shop],
            ]],
            ['name' => 'Lisinopril 10mg', 'category' => 'Blood Pressure', 'selling_price' => 2000, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 20, 'batches' => [
                ['bn' => 'LIS-2026-001', 'cost' => 1000, 'qty' => 3, 'expiry' => now()->addMonths(14), 'loc' => $shop],
            ]],
            ['name' => 'Expired Test Drug', 'category' => 'Antibiotics', 'selling_price' => 500, 'wholesale_price' => null, 'wholesale_min_qty' => null, 'reorder_level' => 10, 'batches' => [
                ['bn' => 'EXP-2025-001', 'cost' => 200, 'qty' => 8, 'expiry' => now()->subDays(10), 'loc' => $warehouse],
            ]],
        ];

        $products = [];
        foreach ($productsData as $pData) {
            $product = Product::firstOrCreate(['name' => $pData['name']], [
                'category_id' => $categories[$pData['category']]->id,
                'selling_price' => $pData['selling_price'],
                'wholesale_price' => $pData['wholesale_price'],
                'wholesale_min_qty' => $pData['wholesale_min_qty'],
                'reorder_level' => $pData['reorder_level'],
            ]);

            foreach ($pData['batches'] as $bData) {
                $batch = Batch::firstOrCreate([
                    'product_id' => $product->id,
                    'batch_number' => $bData['bn'],
                ], [
                    'location_id' => $bData['loc']->id,
                    'cost_price' => $bData['cost'],
                    'quantity' => $bData['qty'],
                    'expiry_date' => $bData['expiry'],
                ]);

                StockMovement::firstOrCreate([
                    'batch_id' => $batch->id,
                    'type' => 'purchase',
                    'reference' => 'Initial stock',
                ], [
                    'quantity' => $bData['qty'],
                    'to_location_id' => $bData['loc']->id,
                    'user_id' => $admin->id,
                ]);
            }

            $products[$pData['name']] = $product;
        }

        // Customers
        $customers = [];
        foreach ([
            ['name' => 'Adeola Johnson', 'type' => 'retail', 'phone' => '08011112222', 'email' => 'adeola@gmail.com'],
            ['name' => 'Musa Ibrahim', 'type' => 'retail', 'phone' => '08033334444', 'email' => null],
            ['name' => 'Ngozi Okafor', 'type' => 'retail', 'phone' => '08055556666', 'email' => 'ngozi.ok@yahoo.com'],
            ['name' => 'St. Mary Hospital', 'type' => 'wholesale', 'phone' => '08077778888', 'email' => 'pharmacy@stmary.org'],
            ['name' => 'Prime Health Clinic', 'type' => 'wholesale', 'phone' => '08099990000', 'email' => 'orders@primehealth.ng'],
            ['name' => 'Fatima Abdullahi', 'type' => 'retail', 'phone' => '08012340000', 'email' => null],
        ] as $data) {
            $customers[$data['name']] = Customer::firstOrCreate(['name' => $data['name']], $data);
        }

        // Sales (mix of payment methods)
        $salesData = [
            ['customer' => 'Adeola Johnson', 'method' => 'cash', 'items' => [['Paracetamol 500mg', 5], ['Vitamin C 1000mg', 2]], 'days_ago' => 0],
            ['customer' => 'Musa Ibrahim', 'method' => 'transfer', 'items' => [['Amoxicillin 500mg', 3], ['Cough Syrup (Benylin)', 1]], 'days_ago' => 0],
            ['customer' => null, 'method' => 'cash', 'items' => [['Paracetamol 500mg', 10], ['Ibuprofen 400mg', 5]], 'days_ago' => 1],
            ['customer' => 'St. Mary Hospital', 'method' => 'transfer', 'items' => [['Amoxicillin 500mg', 50], ['Ciprofloxacin 500mg', 30], ['Metformin 500mg', 20]], 'days_ago' => 2],
            ['customer' => 'Ngozi Okafor', 'method' => 'cash', 'items' => [['Cold Cap (Procold)', 2], ['Antiseptic Solution (Dettol)', 1]], 'days_ago' => 3],
            ['customer' => 'Prime Health Clinic', 'method' => 'split', 'items' => [['Amlodipine 5mg', 25], ['Paracetamol 500mg', 100]], 'days_ago' => 4],
            ['customer' => 'Fatima Abdullahi', 'method' => 'credit', 'items' => [['Artemether/Lumefantrine (Coartem)', 2], ['Paracetamol 500mg', 10]], 'days_ago' => 1],
            ['customer' => null, 'method' => 'card', 'items' => [['Baby Gripe Water', 2], ['Adhesive Bandage (Pack)', 3]], 'days_ago' => 5],
            ['customer' => 'Adeola Johnson', 'method' => 'cash', 'items' => [['Calamine Lotion', 1]], 'days_ago' => 6],
            ['customer' => 'Musa Ibrahim', 'method' => 'credit', 'items' => [['Insulin Glargine (Pen)', 1], ['Metformin 500mg', 3]], 'days_ago' => 3],
        ];

        foreach ($salesData as $sData) {
            $customer = $sData['customer'] ? $customers[$sData['customer']] : null;
            $isWholesale = $customer && $customer->type === 'wholesale';
            $totalAmount = 0;
            $itemsToCreate = [];

            foreach ($sData['items'] as [$productName, $qty]) {
                $product = $products[$productName];
                $batch = $product->batches()->where('quantity', '>', 0)->orderBy('expiry_date')->first();
                if (!$batch || $batch->quantity < $qty) continue;

                $price = $isWholesale && $product->wholesale_price ? (float) $product->wholesale_price : (float) $product->selling_price;
                $subtotal = $price * $qty;
                $totalAmount += $subtotal;

                $itemsToCreate[] = [
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'cost_price' => $batch->cost_price,
                    'subtotal' => $subtotal,
                ];
            }

            if (empty($itemsToCreate)) continue;

            $paymentDetails = null;
            if ($sData['method'] === 'split') {
                $half = round($totalAmount / 2, 2);
                $paymentDetails = ['cash' => $half, 'transfer' => $totalAmount - $half];
            }

            $sale = Sale::create([
                'user_id' => $sData['days_ago'] % 2 === 0 ? $cashier->id : $pharmacist->id,
                'customer_id' => $customer?->id,
                'total_amount' => $totalAmount,
                'payment_method' => $sData['method'],
                'payment_details' => $paymentDetails,
                'status' => 'completed',
                'created_at' => now()->subDays($sData['days_ago']),
                'updated_at' => now()->subDays($sData['days_ago']),
            ]);

            foreach ($itemsToCreate as $itemData) {
                SaleItem::create(array_merge($itemData, ['sale_id' => $sale->id]));
                Batch::where('id', $itemData['batch_id'])->decrement('quantity', $itemData['quantity']);
                StockMovement::create([
                    'batch_id' => $itemData['batch_id'],
                    'quantity' => -$itemData['quantity'],
                    'type' => 'sale',
                    'reference' => 'Sale #' . $sale->id,
                    'user_id' => $sale->user_id,
                ]);
            }

            if ($sData['method'] === 'credit' && $customer) {
                Debt::create([
                    'sale_id' => $sale->id,
                    'customer_id' => $customer->id,
                    'amount_owed' => $totalAmount,
                    'status' => 'unpaid',
                ]);
            }
        }

        // Purchase Order
        $po = PurchaseOrder::firstOrCreate(['po_number' => 'PO-202606-0001'], [
            'supplier_id' => $suppliers[0]->id,
            'user_id' => $admin->id,
            'status' => 'sent',
            'total_amount' => 250000,
            'expected_date' => now()->addDays(7),
            'note' => 'Monthly restock order',
        ]);

        if ($po->items()->count() === 0) {
            PurchaseOrderItem::create(['purchase_order_id' => $po->id, 'product_id' => $products['Amoxicillin 500mg']->id, 'quantity_ordered' => 100, 'unit_cost' => 800, 'subtotal' => 80000]);
            PurchaseOrderItem::create(['purchase_order_id' => $po->id, 'product_id' => $products['Ciprofloxacin 500mg']->id, 'quantity_ordered' => 50, 'unit_cost' => 1100, 'subtotal' => 55000]);
            PurchaseOrderItem::create(['purchase_order_id' => $po->id, 'product_id' => $products['Paracetamol 500mg']->id, 'quantity_ordered' => 500, 'unit_cost' => 80, 'subtotal' => 40000]);
        }

        // Appointments
        Appointment::firstOrCreate(['title' => 'Blood Pressure Check', 'customer_id' => $customers['Adeola Johnson']->id], [
            'user_id' => $pharmacist->id,
            'description' => 'Routine BP monitoring',
            'scheduled_at' => now()->addHours(3),
            'duration_minutes' => 15,
            'status' => 'scheduled',
        ]);

        Appointment::firstOrCreate(['title' => 'Medication Review', 'customer_id' => $customers['Ngozi Okafor']->id], [
            'user_id' => $pharmacist->id,
            'description' => 'Review current prescriptions and check for interactions',
            'scheduled_at' => now()->addDay()->setHour(10),
            'duration_minutes' => 30,
            'status' => 'scheduled',
        ]);

        Appointment::firstOrCreate(['title' => 'Diabetes Consultation', 'customer_id' => $customers['Fatima Abdullahi']->id], [
            'user_id' => $pharmacist->id,
            'description' => 'Follow-up on insulin dosage',
            'scheduled_at' => now()->subDay()->setHour(14),
            'duration_minutes' => 30,
            'status' => 'completed',
        ]);

        // App Settings
        AppSetting::set('pharmacy_name', 'BasmelCare Pharmacy');
        AppSetting::set('pharmacy_phone', '08012345678');
        AppSetting::set('pharmacy_email', 'info@basmelcare.com');
        AppSetting::set('pharmacy_address', '15 Health Avenue, Victoria Island, Lagos');
        AppSetting::set('currency_symbol', '₦');
        AppSetting::set('notify_low_stock', '1');
        AppSetting::set('notify_expiry', '1');
        AppSetting::set('expiry_alert_days', '90');
    }
}
