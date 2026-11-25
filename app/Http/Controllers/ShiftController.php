<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $activeShift = CashShift::with('user')->where('status', 'OPEN')->latest('opened_at')->first();

        $shiftStats = null;
        if ($activeShift) {
            $paidTx = Transaction::where('shift_id', $activeShift->id)
                ->where('status', 'PAID')
                ->get();

            $cashSales = (float) $paidTx->where('payment_method', 'TUNAI')->sum('total_amount');
            $qrisSales = (float) $paidTx->where('payment_method', 'QRIS')->sum('total_amount');

            $shiftStats = [
                'tx_count' => $paidTx->count(),
                'cash_sales' => $cashSales,
                'qris_sales' => $qrisSales,
                'expected_cash' => (float) $activeShift->opening_cash + $cashSales,
            ];
        }

        $closedShifts = CashShift::with('user')
            ->where('status', 'CLOSED')
            ->latest('closed_at')
            ->limit(10)
            ->get();

        return view('shifts.index', compact('activeShift', 'shiftStats', 'closedShifts'));
    }

    public function open(Request $request)
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        if (CashShift::where('status', 'OPEN')->exists()) {
            return back()->withErrors(['opening_cash' => 'Masih ada shift kasir yang berstatus OPEN. Tutup shift sebelumnya terlebih dahulu.']);
        }

        CashShift::create([
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'opening_cash' => $validated['opening_cash'],
            'status' => 'OPEN',
        ]);

        return back()->with('success', 'Shift kasir dibuka. Seluruh transaksi berikutnya otomatis masuk ke shift ini.');
    }

    public function close(Request $request, CashShift $shift)
    {
        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($shift->status === 'CLOSED') {
            return back()->withErrors(['actual_cash' => 'Shift ini sudah ditutup sebelumnya.']);
        }

        $cashSales = (float) Transaction::where('shift_id', $shift->id)
            ->where('status', 'PAID')
            ->where('payment_method', 'TUNAI')
            ->sum('total_amount');

        $expectedCash = (float) $shift->opening_cash + $cashSales;
        $actualCash = (float) $validated['actual_cash'];
        $discrepancy = $actualCash - $expectedCash;

        if ($discrepancy != 0.0 && empty(trim((string) ($validated['notes'] ?? '')))) {
            return back()
                ->withInput()
                ->withErrors(['notes' => 'Terdapat selisih kas. Wajib mengisi catatan penjelasan sebelum menutup shift.']);
        }

        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $expectedCash,
            'actual_cash' => $actualCash,
            'discrepancy' => $discrepancy,
            'notes' => $validated['notes'] ?? null,
            'status' => 'CLOSED',
        ]);

        return back()->with('success', 'Shift kasir berhasil ditutup dan laporan laci uang telah dikunci.');
    }
}