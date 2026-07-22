<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin   = $this->seedStaff();
        $branch  = $this->seedBranch();
        $products = $this->seedCatalog();
        $customers = $this->seedCustomers();

        $this->seedSales($admin, $branch, $products, $customers);
        $this->seedSettings();
    }

    // ── Staff ──────────────────────────────────────────────────────────

    private function seedStaff(): User
    {
        $accounts = [
            ['name' => 'Demo Admin',      'email' => 'admin@demo.com',      'role' => ['admin']],
            ['name' => 'Chidi Okafor',    'email' => 'pharmacist@demo.com', 'role' => ['pharmacist']],
            ['name' => 'Amaka Eze',       'email' => 'cashier@demo.com',    'role' => ['cashier']],
            ['name' => 'Emeka Nwosu',     'email' => 'sales@demo.com',      'role' => ['sales']],
            ['name' => 'Ngozi Adeyemi',   'email' => 'manager@demo.com',    'role' => ['branch_manager']],
            ['name' => 'Tunde Bello',     'email' => 'inventory@demo.com',  'role' => ['inventory_manager']],
            ['name' => 'Blessing Obi',    'email' => 'multi@demo.com',      'role' => ['pharmacist', 'cashier']],
        ];

        foreach ($accounts as $a) {
            User::firstOrCreate(['email' => $a['email']], [
                'name'            => $a['name'],
                'password'        => bcrypt('demo1234'),
                'role'            => $a['role'],
                'status'          => 'active',
                'employment_date' => now()->subMonths(rand(3, 24))->toDateString(),
            ]);
        }

        return User::where('email', 'admin@demo.com')->first();
    }

    // ── Branch ─────────────────────────────────────────────────────────

    private function seedBranch(): Branch
    {
        return Branch::firstOrCreate(['name' => 'Main Branch'], [
            'address' => '12 Pharmacy Road, Ikeja, Lagos',
            'phone'   => '08012345678',
            'is_main' => true,
        ]);
    }

    // ── Categories & Products ───────────────────────────────────────────

    private function seedCatalog(): \Illuminate\Support\Collection
    {
        $cats = collect([
            'Antibiotics',
            'Analgesics & Antipyretics',
            'Vitamins & Supplements',
            'Antimalarials',
            'Gastrointestinal',
        ])->mapWithKeys(fn($name) => [$name => Category::firstOrCreate(['name' => $name])]);

        $catalog = [
            // name                           cat                          price   cost   qty   expiry months
            ['Amoxicillin 500mg',             'Antibiotics',               450,    280,   200,  18],
            ['Cotrimoxazole 480mg',           'Antibiotics',               180,    100,   150,  24],
            ['Metronidazole 200mg',           'Antibiotics',               120,    70,    180,  20],
            ['Paracetamol 500mg (Emzor)',     'Analgesics & Antipyretics', 50,     25,    500,  30],
            ['Ibuprofen 400mg',               'Analgesics & Antipyretics', 80,     45,    300,  24],
            ['Aspirin 75mg',                  'Analgesics & Antipyretics', 60,     30,    200,  36],
            ['Vitamin C 100mg',               'Vitamins & Supplements',    100,    55,    400,  24],
            ['Multivitamin Tablets',          'Vitamins & Supplements',    350,    200,   120,  18],
            ['Folic Acid 5mg',                'Vitamins & Supplements',    90,     50,    250,  30],
            ['Artemether/Lumefantrine (6s)',  'Antimalarials',             1200,   800,   80,   18],
            ['Chloroquine Phosphate 250mg',   'Antimalarials',             250,    150,   100,  24],
            ['ORS Sachet',                    'Gastrointestinal',          80,     40,    300,  36],
            ['Omeprazole 20mg',               'Gastrointestinal',          200,    120,   150,  24],
            ['Metoclopramide 10mg',           'Gastrointestinal',          130,    75,    200,  20],
        ];

        $products = collect();

        foreach ($catalog as $i => [$name, $cat, $price, $cost, $qty, $months]) {
            $product = Product::firstOrCreate(['name' => $name], [
                'category_id'   => $cats[$cat]->id,
                'selling_price' => $price,
                'reorder_level' => 20,
                'show_in_shop'  => true,
            ]);

            Batch::firstOrCreate(
                ['product_id' => $product->id, 'batch_number' => 'BCH-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT)],
                [
                    'cost_price'  => $cost,
                    'quantity'    => $qty,
                    'expiry_date' => now()->addMonths($months)->toDateString(),
                ]
            );

            $products->push($product->load('batches'));
        }

        return $products;
    }

    // ── Customers ───────────────────────────────────────────────────────

    private function seedCustomers(): \Illuminate\Support\Collection
    {
        $data = [
            ['name' => 'Emeka Johnson',  'phone' => '08011111111', 'type' => 'individual'],
            ['name' => 'Fatima Bello',   'phone' => '08022222222', 'type' => 'individual'],
            ['name' => 'Chukwu Health',  'phone' => '08033333333', 'type' => 'corporate'],
            ['name' => 'Ngozi Okafor',   'phone' => '08044444444', 'type' => 'individual'],
            ['name' => 'Adebayo Clinic', 'phone' => '08055555555', 'type' => 'corporate'],
        ];

        return collect($data)->map(
            fn($c) => Customer::firstOrCreate(['phone' => $c['phone']], $c)
        );
    }

    // ── Sales / Voucher scenarios ────────────────────────────────────────

    private function seedSales(User $admin, Branch $branch, $products, $customers): void
    {
        // ── Wi-Fi test scenarios (labelled in the note field) ──────────

        // 1. Fresh receipt — no voucher redeemed yet
        $this->createSale($admin, $branch, $products, $customers[0], [
            'note' => '[TEST] Fresh receipt — use this to test first-time Wi-Fi redemption.',
        ]);

        // 2. Already redeemed 1h ago (still active — 23h remaining)
        $this->createSale($admin, $branch, $products, $customers[1], [
            'voucher_redeemed_at' => now()->subHour(),
            'note' => '[TEST] Redeemed 1h ago — use this to test reconnecting within the window.',
        ]);

        // 3. Expired — redeemed 25h ago, 24h window has closed
        $this->createSale($admin, $branch, $products, $customers[2], [
            'voucher_redeemed_at' => now()->subHours(25),
            'note' => '[TEST] Expired — receipt\'s 24h window has passed. Should be rejected.',
        ]);

        // 4. Revoked — staff already pulled the access
        $this->createSale($admin, $branch, $products, $customers[3], [
            'voucher_redeemed_at' => now()->subHours(2),
            'voucher_revoked_at'  => now()->subHour(),
            'note' => '[TEST] Revoked — find this in Sales History and test the Revoke button.',
        ]);

        // 5. Unpaid — should be rejected at the kiosk
        $this->createSale($admin, $branch, $products, $customers[4], [
            'status'  => 'pending',
            'paid_at' => null,
            'note'    => '[TEST] Unpaid sale — should be rejected as invalid at the kiosk.',
        ]);

        // ── Regular training sales ──────────────────────────────────────
        for ($i = 0; $i < 10; $i++) {
            $this->createSale($admin, $branch, $products, $customers->random());
        }
    }

    private function createSale(User $user, Branch $branch, $products, Customer $customer, array $overrides = []): Sale
    {
        $product = $products->random();
        $batch   = $product->batches->first();
        $qty     = rand(1, 5);
        $total   = $product->selling_price * $qty;

        $attributes = array_merge([
            'invoice_number' => Sale::generateInvoiceNumber(),
            'user_id'        => $user->id,
            'customer_id'    => $customer->id,
            'total_amount'   => $total,
            'payment_method' => collect(['cash', 'transfer', 'card'])->random(),
            'status'         => 'completed',
            'paid_at'        => now()->subDays(rand(0, 14)),
        ], $overrides);

        $sale = new Sale($attributes);
        $sale->branch_id = $branch->id;
        $sale->save();

        SaleItem::create([
            'sale_id'    => $sale->id,
            'product_id' => $product->id,
            'batch_id'   => $batch->id,
            'quantity'   => $qty,
            'unit_price' => $product->selling_price,
            'cost_price' => $batch->cost_price,
            'subtotal'   => $total,
        ]);

        return $sale;
    }

    // ── Settings ────────────────────────────────────────────────────────

    private function seedSettings(): void
    {
        AppSetting::set('pharmacy_name', 'BasmelCare Demo Pharmacy');
        AppSetting::set('pharmacy_phone', '08012345678');
        AppSetting::set('pharmacy_address', '12 Pharmacy Road, Ikeja, Lagos');
        AppSetting::set('voucher_validity_hours', '24');
    }
}
