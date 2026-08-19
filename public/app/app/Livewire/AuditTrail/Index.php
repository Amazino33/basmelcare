<?php

namespace App\Livewire\AuditTrail;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\AppSetting;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $type = '';

    #[Url]
    public string $userId = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public string $search = '';

    public function mount(): void
    {
        $this->from = $this->from ?: today()->subDays(30)->format('Y-m-d');
        $this->to   = $this->to ?: today()->format('Y-m-d');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['type', 'userId', 'search']);
        $this->from = today()->subDays(30)->format('Y-m-d');
        $this->to   = today()->format('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $types = [
            Product::class    => 'Product prices',
            Batch::class      => 'Batch cost / quantity',
            Coupon::class     => 'Coupons',
            AppSetting::class => 'Settings',
        ];

        $logs = AuditLog::with('user')
            ->when($this->type, fn($q) => $q->where('auditable_type', $this->type))
            ->when($this->userId, fn($q) => $q->where('user_id', $this->userId))
            ->when($this->from, fn($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->search, fn($q) => $q->where(fn($w) => $w
                ->where('auditable_label', 'like', "%{$this->search}%")
                ->orWhere('field', 'like', "%{$this->search}%")))
            ->latest('created_at')
            ->paginate(30);

        return view('livewire.audit-trail.index', [
            'logs'  => $logs,
            'types' => $types,
            'staff' => User::whereIn('id', AuditLog::distinct()->pluck('user_id')->filter())
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
