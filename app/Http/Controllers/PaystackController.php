<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaystackController extends Controller
{
    public function pay(Order $order)
    {
        if ($order->payment_status === 'paid') {
            return redirect('/order/' . $order->id . '/confirmation');
        }

        $paystackKey = AppSetting::get('paystack_public_key', '');

        return view('public.paystack-pay', [
            'order' => $order,
            'paystackKey' => $paystackKey,
            'email' => $order->customer?->email ?? $order->guest_email ?? ($order->guest_phone . '@guest.basmelcare.com'),
            'amount' => $order->total_amount * 100,
            'reference' => 'BMC-' . $order->id . '-' . time(),
        ]);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        $secretKey = AppSetting::get('paystack_secret_key', '');

        $response = Http::withToken($secretKey)->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('data.status') === 'success') {
            $metadata = $response->json('data.metadata', []);
            $orderId = $metadata['order_id'] ?? null;

            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_reference' => $reference,
                        'paid_at' => now(),
                    ]);

                    return redirect('/order/' . $order->id . '/confirmation');
                }
            }
        }

        return redirect('/account')->with('error', 'Payment verification failed.');
    }
}
