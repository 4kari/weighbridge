<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\Truck;
use App\Models\WeighbridgeTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesOrderController extends Controller
{
    # Task 4 - Buat tiket ritase lanjutan jika Sales Order masih memiliki sisa muatan.
    public function createNextTicket(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $validated = $request->validate([
            'truck_id' => ['required', 'integer', 'exists:trucks,id'],
        ]);

        return DB::transaction(function () use ($salesOrder, $validated): RedirectResponse {
            $salesOrder = SalesOrder::query()->lockForUpdate()->findOrFail($salesOrder->id);

            if ($salesOrder->status === SalesOrder::STATUS_COMPLETED || $salesOrder->remaining_qty_kg <= 0) {
                return back()->with('error', 'Sales Order tidak memiliki sisa muatan.');
            }

            $truck = Truck::findOrFail($validated['truck_id']);

            $salesOrder->weighbridgeTickets()->create([
                'ticket_number' => 'WB-'.now()->year.'-'.Str::ulid(),
                'truck_id' => $truck->id,
                'tare_weight_kg' => $truck->tare_weight_kg,
                'status' => WeighbridgeTicket::STATUS_PENDING,
            ]);

            return back()->with('success', 'Tiket ritase lanjutan berhasil dibuat.');
        });
    }
}
