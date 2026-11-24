<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\Truck;
use App\Models\WeighbridgeTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    # Task 2 - Penimbangan akhir untuk tiket yang masih menunggu ditimbang.
    public function weigh(Request $request, WeighbridgeTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'gross_weight_kg' => ['required', 'numeric', 'decimal:0,2', 'gt:'.$ticket->tare_weight_kg, 'max:9999999999999.99'],
        ]);

        return $this->recordWeighing($ticket, (float) $validated['gross_weight_kg'], WeighbridgeTicket::STATUS_PENDING);
    }

    # Task 3 - Timbang ulang tiket yang ditahan tanpa membatalkan Sales Order.
    public function resolveOverload(Request $request, WeighbridgeTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'new_gross_weight_kg' => ['required', 'numeric', 'decimal:0,2', 'gt:'.$ticket->tare_weight_kg, 'max:9999999999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->recordWeighing(
            $ticket,
            (float) $validated['new_gross_weight_kg'],
            WeighbridgeTicket::STATUS_OVERLOAD_HOLD,
            $validated['notes'] ?? null,
        );
    }

    # Task 1 - Terbitkan Surat Jalan untuk tiket yang lolos timbang dan cegah penerbitan ganda.
    public function dispatch(WeighbridgeTicket $ticket): RedirectResponse
    {
        return DB::transaction(function () use ($ticket): RedirectResponse {
            $ticket = WeighbridgeTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status === WeighbridgeTicket::STATUS_DISPATCHED || $ticket->deliveryNote()->exists()) {
                return back()->with('error', 'Surat Jalan untuk tiket ini sudah diterbitkan.');
            }

            $netWeight = $ticket->net_weight_kg;

            if ($ticket->status !== WeighbridgeTicket::STATUS_APPROVED
                || $ticket->is_overload
                || $netWeight <= 0
                || $netWeight > $ticket->truck->max_net_weight_kg) {
                return back()->with('error', 'Tiket belum lolos penimbangan.');
            }

            $salesOrder = $ticket->salesOrder()->lockForUpdate()->firstOrFail();
            $newShippedQty = round($salesOrder->shipped_qty_kg + $netWeight, 2);

            $ticket->deliveryNote()->create([
                'delivery_number' => 'SJ-'.now()->format('YmdHis').'-'.$ticket->id,
                'sales_order_id' => $ticket->sales_order_id,
                'truck_id' => $ticket->truck_id,
                'dispatched_qty_kg' => $netWeight,
                'issued_at' => now(),
            ]);

            $ticket->update(['status' => WeighbridgeTicket::STATUS_DISPATCHED]);
            $salesOrder->update([
                'shipped_qty_kg' => $newShippedQty,
                'status' => $newShippedQty >= $salesOrder->ordered_qty_kg
                    ? SalesOrder::STATUS_COMPLETED
                    : SalesOrder::STATUS_PARTIALLY_SHIPPED,
            ]);

            return back()->with('success', 'Surat Jalan berhasil diterbitkan.');
        });
    }

    
    # Task 2 dan 3 - Hitung berat bersih dan tentukan status tiket berdasarkan kapasitas truk.    
    private function recordWeighing(WeighbridgeTicket $ticket, float $grossWeight, string $requiredStatus, ?string $notes = null): RedirectResponse
    {
        return DB::transaction(function () use ($ticket, $grossWeight, $requiredStatus, $notes): RedirectResponse {
            $ticket = WeighbridgeTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status !== $requiredStatus) {
                return back()->with('error', 'Status tiket tidak mengizinkan penimbangan ini.');
            }

            $netWeight = round($grossWeight - $ticket->tare_weight_kg, 2);
            $overload = max(0, round($netWeight - $ticket->truck->max_net_weight_kg, 2));

            $ticket->update([
                'gross_weight_kg' => $grossWeight,
                'net_weight_kg' => $netWeight,
                'is_overload' => $overload > 0,
                'overload_kg' => $overload,
                'status' => $overload > 0 ? WeighbridgeTicket::STATUS_OVERLOAD_HOLD : WeighbridgeTicket::STATUS_APPROVED,
                'notes' => $notes ?? $ticket->notes,
                'weighed_at' => now(),
            ]);

            if ($overload > 0) {
                return back()->with('error', 'Muatan melebihi kapasitas truk. Tiket ditahan untuk timbang ulang.');
            }

            return back()->with('success', 'Penimbangan berhasil. Tiket siap untuk penerbitan Surat Jalan.');
        });
    }
}
