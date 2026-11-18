<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SalesOrderController extends Controller
{
    public function createNextTicket(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $request->validate([
            'truck_id' => ['required', 'exists:trucks,id'],
        ]);

        return back()->with('error', 'Belum diimplementasikan.');
    }
}
