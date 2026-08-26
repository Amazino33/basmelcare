<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppSetting;
use App\Support\ConsultationPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Paying for a consultation booked from the shop.
 *
 * Mirrors the order payment flow rather than extending it. One controller
 * handling both would mean every change risking a working payment path, and
 * the two settle different things - an order releases stock, a consultation
 * holds a request.
 */
class ConsultationPaymentController extends Controller
{
    public function pay(Appointment $appointment)
    {
        if ($appointment->isSettled()) {
            return redirect()->route('consultation.confirmation', $appointment);
        }

        // Held only so long. Past that the request is no longer live, and
        // taking money for it would promise something staff are not chasing.
        if (ConsultationPricing::hasLapsed($appointment)) {
            return redirect()->route('consultation.book')
                ->with('error', 'That request was not paid in time. Please book again.');
        }

        return view('public.consultation-pay', [
            'appointment' => $appointment,
            'paystackKey' => AppSetting::get('paystack_public_key', ''),
            'email'       => $appointment->customer?->email
                ?: (($appointment->customer?->phone ?? 'guest') . '@guest.basmelcare.com'),
            'amount'      => $appointment->price * 100,
            'reference'   => 'BMC-CONSULT-' . $appointment->id . '-' . time(),
        ]);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        $secretKey = AppSetting::get('paystack_secret_key', '');

        $response = Http::withToken($secretKey)
            ->get('https://api.paystack.co/transaction/verify/' . $reference);

        if ($response->successful() && $response->json('data.status') === 'success') {
            $appointmentId = $response->json('data.metadata.appointment_id');

            if ($appointmentId && $appointment = Appointment::find($appointmentId)) {
                // Verified against Paystack rather than trusted from the
                // browser: the callback URL is just a redirect and anyone can
                // visit it with any reference.
                $appointment->update([
                    'payment_status'    => 'paid',
                    'payment_reference' => $reference,
                    'paid_at'           => now(),
                ]);

                return redirect()->route('consultation.confirmation', $appointment);
            }
        }

        return redirect()->route('consultation.book')
            ->with('error', 'We could not confirm that payment. Nothing has been charged twice — please try again.');
    }

    public function confirmation(Appointment $appointment)
    {
        return view('public.consultation-confirmation', ['appointment' => $appointment]);
    }
}
