<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Support\TopProducts;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The printable Top Products sheet.
 *
 * Guarded here as well as in the route. Revenue and profit rankings expose
 * margin, and a printable page is exactly the kind of URL that gets passed
 * around: the route middleware is the fence, this is the lock.
 */
class TopProductsPrintController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            array_intersect($request->user()->role ?? [], ['admin', 'branch_manager']),
            403
        );

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        // Whole days, so a sheet run "to today" includes everything sold today.
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfMonth();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        return view('reports.top-products-print', [
            // Ten rather than the dashboard's five: a sheet of paper has room,
            // and the point of printing is to study it away from the screen.
            'top'          => TopProducts::between($from, $to, 10),
            'from'         => $from,
            'to'           => $to,
            'pharmacyName' => AppSetting::get('pharmacy_name', 'BasmelCare Pharmacy'),
            'printedAt'    => now()->format('d M Y, g:i A'),
            'printedBy'    => $request->user()->name,
        ]);
    }
}
