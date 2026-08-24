<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Serves a customer's uploaded prescription to staff who need to see it.
 *
 * Previously the file was linked by its storage URL, which is a plain
 * unauthenticated address: anyone holding or guessing it could read a
 * patient's prescription. Going through a route means the request is
 * authenticated and role-checked, and the address alone is not enough.
 *
 * The underlying file still sits in web-servable storage, so a direct URL
 * would still resolve. Closing that fully means moving prescriptions out of
 * the public disk, which is a separate change - this removes the linked path
 * and puts a check in front of the one people actually use.
 */
class PrescriptionFileController extends Controller
{
    /** Reviewing pharmacists, and the staff who prepare the order. */
    private const MAY_VIEW = ['pharmacist', 'admin', 'branch_manager', 'sales'];

    public function __invoke(Request $request, Order $order)
    {
        abort_unless(
            array_intersect($request->user()->role ?? [], self::MAY_VIEW),
            403
        );

        abort_unless($order->prescription_path, 404);

        $disk = Storage::disk('public_site');

        abort_unless($disk->exists($order->prescription_path), 404);

        // Inline rather than as a download: a pharmacist is reading it, not
        // filing it, and a download would leave patient documents scattered
        // through the downloads folder of every machine in the shop.
        return $disk->response(
            $order->prescription_path,
            'prescription-' . $order->order_number . '.' . pathinfo($order->prescription_path, PATHINFO_EXTENSION),
            ['Content-Disposition' => 'inline']
        );
    }
}
