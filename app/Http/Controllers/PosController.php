<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModifier;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();
        $products = Product::with('category')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'category_id' => $p->category_id,
                'category_name' => $p->category->name,
                'name' => $p->name,
                'price' => (float) $p->price,
                'is_available' => (bool) $p->is_available,
            ]);

        $modifiers = ProductModifier::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'price' => (float) $m->price,
            ]);

        return view('pos.index', compact('categories', 'products', 'modifiers'));
    }

    public function checkout(Request $request, CheckoutService $checkoutService)
    {
        $validated = $request->validate([
            'id' => ['nullable', 'uuid'],
            'order_type' => ['required', 'in:DINE_IN,TAKEAWAY'],
            'payment_method' => ['required', 'in:TUNAI,QRIS'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'is_offline_synced' => ['nullable', 'boolean'],
            'created_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'items.*.modifier_ids.*' => ['uuid', 'exists:product_modifiers,id'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
        ]);

        $transaction = $checkoutService->processCheckout($validated, $request->user()->id);

        return response()->json([
            'message' => 'Transaksi berhasil disimpan.',
            'transaction' => [
                'id' => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'order_type' => $transaction->order_type,
                'payment_method' => $transaction->payment_method,
                'subtotal' => (float) $transaction->subtotal,
                'total_amount' => (float) $transaction->total_amount,
                'cash_received' => (float) $transaction->cash_received,
                'change_amount' => (float) $transaction->change_amount,
                'cashier_name' => $transaction->user->name,
                'created_at' => $transaction->created_at->format('d/m/Y H:i'),
                'items' => $transaction->items->map(fn ($item) => [
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) ($item->unit_price * $item->quantity),
                    'modifiers' => collect($item->modifiers_json ?? [])->pluck('name')->all(),
                    'notes' => $item->notes,
                ]),
            ],
        ]);
    }
}