<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@basmelcare.com'], [
            'name'            => 'Admin',
            'password'        => bcrypt('password'),
            'role'            => ['admin'],
            'status'          => 'active',
            'employment_date' => now()->toDateString(),
        ]);

        AppSetting::set('currency_symbol', '₦');
        AppSetting::set('notify_low_stock', '1');
        AppSetting::set('notify_expiry', '1');
        AppSetting::set('expiry_alert_days', '90');
    }
}
