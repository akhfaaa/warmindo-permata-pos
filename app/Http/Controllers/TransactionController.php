<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $transactions = Transaction::with(['items.product', 'user'])
            ->whereDate('created_at', $date)
            ->latest()
            ->get();

        $summary = [
            'count' => $transactions->where('status', 'PAID')->count(),
            'total_paid' => (float) $transactions->where('status', 'PAID')->sum('total_amount'),
            'total_tunai' => (float) $transactions->where('status', 'PAID')->where('payment_method', 'TUNAI')->sum('total_amount'),
            'total_qris' => (float) $transactions->where('status', 'PAID')->where('payment_method', 'QRIS')->sum('total_amount'),
        ];

        return view('transactions.index', compact('transactions', 'summary', 'date'));
    }

    public function void(Request $request, Transaction $transaction, CheckoutService $checkoutService)
    {
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        $checkoutService->voidTransaction($transaction, $validated['void_reason']);

        return back()->with('success', "Nota {$transaction->invoice_number} dibatalkan (VOID) dan stok bahan baku telah dikembalikan.");
    }
}