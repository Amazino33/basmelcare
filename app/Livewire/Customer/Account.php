<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
class Account extends Component
{
    public string $activeTab = 'overview';

    public function logout()
    {
        Auth::guard('customer')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect('/');
    }

    public function render()
    {
        $customer = Auth::guard('customer')->user();

        $recentSales = $customer->sales()->with('saleItems.product')->latest()->limit(5)->get();
        $allSales = $customer->sales()->with('saleItems.product')->latest()->get();
        $debts = $customer->debts()->with('sale', 'payments')->latest()->get();
        $appointments = $customer->appointments()->with('staff')->latest()->get();
        $medicalRecords = $customer->medicalRecords()->with('recorder')->latest()->get();

        $totalSpent = $customer->sales()->where('status', 'completed')->sum('total_amount');
        $totalDebt = $customer->totalDebt;
        $totalOrders = $customer->sales()->count();

        return view('livewire.customer.account', [
            'customer' => $customer,
            'recentSales' => $recentSales,
            'allSales' => $allSales,
            'debts' => $debts,
            'appointments' => $appointments,
            'medicalRecords' => $medicalRecords,
            'totalSpent' => $totalSpent,
            'totalDebt' => $totalDebt,
            'totalOrders' => $totalOrders,
        ]);
    }
}
