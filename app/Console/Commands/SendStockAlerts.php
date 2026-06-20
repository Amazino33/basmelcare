<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Batch;
use App\Models\Product;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendStockAlerts extends Command
{
    protected $signature = 'pharmacy:stock-alerts';
    protected $description = 'Send WhatsApp alerts for low stock and expiring products';

    public function handle(WhatsAppService $whatsApp): void
    {
        if (!AppSetting::bool('notify_low_stock') && !AppSetting::bool('notify_expiry')) {
            $this->info('Notifications disabled.');
            return;
        }

        $phone = AppSetting::get('pharmacy_phone');
        if (!$phone) {
            $this->warn('No pharmacy phone configured in settings.');
            return;
        }

        $messages = [];

        if (AppSetting::bool('notify_low_stock', true)) {
            $lowStock = Product::with('batches')->get()
                ->filter(fn($p) => $p->batches->sum('quantity') <= $p->reorder_level && $p->batches->sum('quantity') > 0);

            $outOfStock = Product::with('batches')->get()
                ->filter(fn($p) => $p->batches->sum('quantity') == 0);

            if ($lowStock->count()) {
                $items = $lowStock->map(fn($p) => "- {$p->name}: {$p->batches->sum('quantity')} left (reorder: {$p->reorder_level})")->join("\n");
                $messages[] = "⚠️ LOW STOCK ALERT\n\n{$lowStock->count()} product(s) below reorder level:\n\n{$items}";
            }

            if ($outOfStock->count()) {
                $items = $outOfStock->take(10)->map(fn($p) => "- {$p->name}")->join("\n");
                $messages[] = "❌ OUT OF STOCK\n\n{$outOfStock->count()} product(s) have zero stock:\n\n{$items}";
            }
        }

        if (AppSetting::bool('notify_expiry', true)) {
            $days = (int) AppSetting::get('expiry_alert_days', 90);

            $expiring = Batch::with('product')
                ->where('quantity', '>', 0)
                ->where('expiry_date', '<=', now()->addDays($days))
                ->where('expiry_date', '>=', now())
                ->orderBy('expiry_date')
                ->get();

            $expired = Batch::with('product')
                ->where('quantity', '>', 0)
                ->where('expiry_date', '<', now())
                ->get();

            if ($expiring->count()) {
                $items = $expiring->take(10)->map(fn($b) => "- {$b->product->name} ({$b->batch_number}): expires {$b->expiry_date->format('M d, Y')}")->join("\n");
                $messages[] = "⏰ EXPIRY ALERT\n\n{$expiring->count()} batch(es) expiring within {$days} days:\n\n{$items}";
            }

            if ($expired->count()) {
                $items = $expired->take(10)->map(fn($b) => "- {$b->product->name} ({$b->batch_number}): expired {$b->expiry_date->format('M d, Y')}")->join("\n");
                $messages[] = "🚫 EXPIRED STOCK\n\n{$expired->count()} batch(es) have expired:\n\n{$items}";
            }
        }

        if (empty($messages)) {
            $this->info('No alerts to send.');
            return;
        }

        foreach ($messages as $msg) {
            $sent = $whatsApp->send($phone, $msg);
            $this->info($sent ? 'Sent alert.' : 'Failed to send (check logs).');
        }
    }
}
