<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductModifier;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * Memproses transaksi kasir secara atomik (ACID):
     * 1. Cek idempotency (mencegah duplikasi saat sync offline di Fase 4)
     * 2. Hitung total & snapshot HPP per item (menu + topping)
     * 3. Potong stok bahan baku otomatis (izinkan minus jika stok sistem telat di-input)
     * 4. Catat ledger mutasi stok (OUT)
     */
    public function processCheckout(array $payload, string $userId): Transaction
    {
        return DB::transaction(function () use ($payload, $userId) {
            $txId = $payload['id'] ?? (string) Str::uuid();

            // Idempotency check: kembalikan transaksi lama jika UUID sudah pernah diproses
            $existing = Transaction::with('items.product')->find($txId);
            if ($existing) {
                return $existing;
            }

            $activeShift = CashShift::where('status', 'OPEN')->latest('opened_at')->first();

            // Generate nomor nota harian: INV-YYYYMMDD-0001
            $todayPrefix = 'INV-' . now()->format('Ymd') . '-';
            $todayCount = Transaction::where('invoice_number', 'like', $todayPrefix . '%')
                ->lockForUpdate()
                ->count();
            $invoiceNumber = $todayPrefix . str_pad((string) ($todayCount + 1), 4, '0', STR_PAD_LEFT);

            $subtotal = 0;
            $preparedItems = [];
            $ingredientDeductions = []; // [ingredient_id => total_qty_to_deduct]

            foreach ($payload['items'] as $item) {
                $product = Product::with('recipes.ingredient')->findOrFail($item['product_id']);
                $qty = (int) $item['quantity'];
                $modifierIds = $item['modifier_ids'] ?? [];

                $modifiers = empty($modifierIds)
                    ? collect()
                    : ProductModifier::with('recipes.ingredient')->whereIn('id', $modifierIds)->get();

                $modifiersTotalPrice = (float) $modifiers->sum('price');
                $unitPriceWithModifiers = (float) $product->price + $modifiersTotalPrice;
                $lineTotal = $unitPriceWithModifiers * $qty;
                $subtotal += $lineTotal;

                // Hitung snapshot HPP per unit (Menu + Topping)
                $unitHpp = $product->estimated_hpp + (float) $modifiers->sum(fn ($m) => $m->estimated_hpp);

                // Akumulasi kebutuhan bahan baku dari resep menu
                foreach ($product->recipes as $recipe) {
                    $ingId = $recipe->ingredient_id;
                    $ingredientDeductions[$ingId] = ($ingredientDeductions[$ingId] ?? 0) + ((float) $recipe->quantity_required * $qty);
                }

                // Akumulasi kebutuhan bahan baku dari resep setiap topping
                foreach ($modifiers as $mod) {
                    foreach ($mod->recipes as $recipe) {
                        $ingId = $recipe->ingredient_id;
                        $ingredientDeductions[$ingId] = ($ingredientDeductions[$ingId] ?? 0) + ((float) $recipe->quantity_required * $qty);
                    }
                }

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPriceWithModifiers,
                    'modifiers_json' => $modifiers->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'price' => (float) $m->price,
                    ])->values()->all(),
                    'item_hpp_snapshot' => round($unitHpp, 2),
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $totalAmount = $subtotal;
            $paymentMethod = $payload['payment_method'];
            $cashReceived = $paymentMethod === 'TUNAI'
                ? (float) ($payload['cash_received'] ?? 0)
                : $totalAmount;

            if ($paymentMethod === 'TUNAI' && $cashReceived < $totalAmount) {
                throw ValidationException::withMessages([
                    'cash_received' => 'Nominal uang tunai kurang dari total tagihan.',
                ]);
            }

            $changeAmount = $paymentMethod === 'TUNAI' ? max(0, $cashReceived - $totalAmount) : 0;

            $transaction = Transaction::create([
                'id' => $txId,
                'invoice_number' => $invoiceNumber,
                'user_id' => $userId,
                'shift_id' => $activeShift?->id,
                'order_type' => $payload['order_type'] ?? 'DINE_IN',
                'payment_method' => $paymentMethod,
                'subtotal' => $subtotal,
                'total_amount' => $totalAmount,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'status' => 'PAID',
                'is_offline_synced' => (bool) ($payload['is_offline_synced'] ?? false),
                'created_at' => $payload['created_at'] ?? now(),
            ]);

            foreach ($preparedItems as $pItem) {
                TransactionItem::create(array_merge($pItem, [
                    'transaction_id' => $transaction->id,
                ]));
            }

            // Potong stok bahan baku & catat mutasi OUT
            foreach ($ingredientDeductions as $ingId => $deductQty) {
                $ingredient = Ingredient::lockForUpdate()->find($ingId);
                if (! $ingredient) {
                    continue;
                }

                $ingredient->update([
                    'current_stock' => (float) $ingredient->current_stock - $deductQty,
                ]);

                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => 'OUT',
                    'quantity_change' => -abs($deductQty),
                    'cost_per_unit' => $ingredient->avg_cost_per_unit,
                    'reference_id' => $transaction->id,
                    'notes' => "Penjualan {$invoiceNumber}",
                ]);
            }

            return $transaction->load(['items.product', 'user']);
        });
    }

    /**
     * Pembatalan transaksi (VOID) oleh Owner + pengembalian otomatis stok bahan baku.
     */
    public function voidTransaction(Transaction $transaction, string $reason): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $tx = Transaction::with(['items.product.recipes'])->lockForUpdate()->findOrFail($transaction->id);

            if ($tx->status === 'VOID') {
                return $tx;
            }

            $tx->update([
                'status' => 'VOID',
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            // Kembalikan stok berdasarkan resep produk & topping yang tercatat di item transaksi
            $reversals = [];
            foreach ($tx->items as $item) {
                $qty = (int) $item->quantity;

                if ($item->product) {
                    foreach ($item->product->recipes as $recipe) {
                        $ingId = $recipe->ingredient_id;
                        $reversals[$ingId] = ($reversals[$ingId] ?? 0) + ((float) $recipe->quantity_required * $qty);
                    }
                }

                $modifierIds = collect($item->modifiers_json ?? [])->pluck('id')->filter()->all();
                if (! empty($modifierIds)) {
                    $modifiers = ProductModifier::with('recipes')->whereIn('id', $modifierIds)->get();
                    foreach ($modifiers as $mod) {
                        foreach ($mod->recipes as $recipe) {
                            $ingId = $recipe->ingredient_id;
                            $reversals[$ingId] = ($reversals[$ingId] ?? 0) + ((float) $recipe->quantity_required * $qty);
                        }
                    }
                }
            }

            foreach ($reversals as $ingId => $restoreQty) {
                $ingredient = Ingredient::lockForUpdate()->find($ingId);
                if (! $ingredient) {
                    continue;
                }

                $ingredient->update([
                    'current_stock' => (float) $ingredient->current_stock + $restoreQty,
                ]);

                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => 'IN',
                    'quantity_change' => abs($restoreQty),
                    'cost_per_unit' => $ingredient->avg_cost_per_unit,
                    'reference_id' => $tx->id,
                    'notes' => "Reversal VOID {$tx->invoice_number}: {$reason}",
                ]);
            }

            return $tx;
        });
    }
}