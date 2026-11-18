<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\Truck;
use App\Models\WeighbridgeTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeighbridgeController extends Controller
{
    public function index(): View
    {
        $tickets = WeighbridgeTicket::with(['salesOrder', 'truck', 'deliveryNote'])
            ->orderBy('id')
            ->get();

        $salesOrders = SalesOrder::orderBy('id')->get();
        $trucks = Truck::orderBy('id')->get();
        $deliveryNotes = DeliveryNote::with(['salesOrder', 'truck', 'weighbridgeTicket'])
            ->latest('issued_at')
            ->get();

        return view('weighbridge.index', compact('tickets', 'salesOrders', 'trucks', 'deliveryNotes'));
    }

    public function weigh(Request $request, WeighbridgeTicket $ticket): RedirectResponse
    {
        $request->validate([
            'gross_weight_kg' => ['required', 'numeric', 'gt:' . $ticket->tare_weight_kg],
        ]);

        return back()->with('error', 'Belum diimplementasikan.');
    }

    public function resolveOverload(Request $request, WeighbridgeTicket $ticket): RedirectResponse
    {
        $request->validate([
            'new_gross_weight_kg' => ['required', 'numeric', 'gt:' . $ticket->tare_weight_kg],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return back()->with('error', 'Belum diimplementasikan.');
    }

    public function dispatch(WeighbridgeTicket $ticket): RedirectResponse
    {
        DeliveryNote::create([
            'delivery_number' => 'SJ-' . now()->format('YmdHis') . '-' . $ticket->id,
            'weighbridge_ticket_id' => $ticket->id,
            'sales_order_id' => $ticket->sales_order_id,
            'truck_id' => $ticket->truck_id,
            'dispatched_qty_kg' => $ticket->net_weight_kg ?? 0,
            'issued_at' => now(),
        ]);

        $ticket->update([
            'status' => WeighbridgeTicket::STATUS_DISPATCHED,
        ]);

        $salesOrder = $ticket->salesOrder;
        $newShippedQty = $salesOrder->shipped_qty_kg + ($ticket->net_weight_kg ?? 0);
        $salesOrder->update([
            'shipped_qty_kg' => $newShippedQty,
            'status' => $newShippedQty >= $salesOrder->ordered_qty_kg
                ? SalesOrder::STATUS_COMPLETED
                : SalesOrder::STATUS_PARTIALLY_SHIPPED,
        ]);

        return back()->with('success', 'Surat Jalan berhasil diterbitkan.');
    }
}
