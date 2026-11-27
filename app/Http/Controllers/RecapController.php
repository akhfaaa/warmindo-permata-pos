<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecapController extends Controller
{
    public function index(Request $request)
    {
        $monthInput = $request->query('month', now()->format('Y-m'));
        try {
            $selectedMonth = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable $e) {
            $selectedMonth = now()->startOfMonth();
        }

        $startOfMonth = $selectedMonth->copy()->startOfMonth();
        $endOfMonth   = $selectedMonth->copy()->endOfMonth();

        // 1. REKAP HARIAN PENJUALAN (Tanggal, Nama Makanan/Minuman (Kategori), Harga, Terjual, Pendapatan)
        $paidTransactions = Transaction::with(['items.product.category'])
            ->where('status', 'PAID')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->orderByDesc('created_at')
            ->get();

        $dailySalesGrouped = [];
        $monthlyTotalSales = 0.0;
        $monthlyTotalPortions = 0;

        foreach ($paidTransactions as $tx) {
            $dateKey = $tx->created_at->format('Y-m-d');
            $dateFormatted = $tx->created_at->translatedFormat('d M Y');

            foreach ($tx->items as $item) {
                $productName  = $item->product?->name ?? 'Menu Dihapus';
                $categoryName = $item->product?->category?->name ?? 'Umum';
                $unitPrice    = (float) $item->unit_price;
                $qty          = (int) $item->quantity;
                $revenue      = $unitPrice * $qty;

                $monthlyTotalSales += $revenue;
                $monthlyTotalPortions += $qty;

                $rowKey = "{$dateKey}|{$item->product_id}|{$unitPrice}";

                if (! isset($dailySalesGrouped[$rowKey])) {
                    $dailySalesGrouped[$rowKey] = [
                        'date_raw'      => $dateKey,
                        'tanggal'       => $dateFormatted,
                        'product_name'  => $productName,
                        'category_name' => $categoryName,
                        'harga'         => $unitPrice,
                        'terjual'       => 0,
                        'pendapatan'    => 0.0,
                    ];
                }

                $dailySalesGrouped[$rowKey]['terjual'] += $qty;
                $dailySalesGrouped[$rowKey]['pendapatan'] += $revenue;
            }
        }

        $dailySalesRecap = collect($dailySalesGrouped)
            ->sortBy([
                ['date_raw', 'desc'],
                ['pendapatan', 'desc'],
            ])
            ->values();

        // 2. REKAP BELANJA / PENGELUARAN (Tanggal, Belanja, Total)
        $costCol = collect(['cost_per_unit', 'average_cost', 'purchase_price', 'unit_cost'])
            ->first(fn (string $col) => Schema::hasColumn('ingredients', $col));

        $qtyCol = $this->resolveMovementQtyColumn();

        $movements = StockMovement::with(['ingredient'])
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->orderByDesc('created_at')
            ->get();

        $purchaseRecap = collect();
        $monthlyTotalExpenses = 0.0;

        foreach ($movements as $mov) {
            $qty = $qtyCol ? (float) ($mov->{$qtyCol} ?? 0) : 0.0;
            $type = strtoupper((string) ($mov->type ?? ''));
            $totalCost = (float) ($mov->total_cost ?? 0);

            $isPurchase = in_array($type, ['RESTOCK', 'IN', 'PURCHASE', 'STOCK_IN'], true)
                || ($totalCost > 0 && $qty > 0)
                || ($type === '' && $qty > 0);

            if (! $isPurchase || $qty <= 0) {
                continue;
            }

            if ($totalCost <= 0) {
                $unitCost = (float) ($mov->unit_cost ?? ($costCol ? ($mov->ingredient?->{$costCol} ?? 0) : 0));
                $totalCost = round($qty * $unitCost, 2);
            }

            $monthlyTotalExpenses += $totalCost;

            $ingName = $mov->ingredient?->name ?? 'Bahan Baku';
            $ingUnit = $mov->ingredient?->unit ?? '';
            $noteText = trim((string) ($mov->notes ?? $mov->reason ?? $mov->description ?? ''));
            $belanjaLabel = "{$ingName} (" . number_format($qty, 0, ',', '.') . " {$ingUnit})";
            if ($noteText !== '' && ! str_contains(strtolower($noteText), strtolower($ingName))) {
                $belanjaLabel .= " — {$noteText}";
            }

            $purchaseRecap->push([
                'id'              => $mov->id,
                'tanggal'         => $mov->created_at->translatedFormat('d M Y'),
                'waktu'           => $mov->created_at->format('H:i'),
                'ingredient_name' => $ingName,
                'unit'            => $ingUnit,
                'quantity'        => $qty,
                'notes'           => $noteText,
                'belanja'         => $belanjaLabel,
                'total'           => $totalCost,
            ]);
        }

        // 3. TOTAL PENJUALAN PER BULAN - TOTAL BELANJA PER BULAN = TOTAL PENDAPATAN
        $monthlyNetIncome = $monthlyTotalSales - $monthlyTotalExpenses;

        return view('recap.index', [
            'selectedMonth'        => $selectedMonth->format('Y-m'),
            'selectedMonthLabel'   => $selectedMonth->translatedFormat('F Y'),
            'dailySalesRecap'      => $dailySalesRecap,
            'purchaseRecap'        => $purchaseRecap,
            'monthlyTotalPortions' => $monthlyTotalPortions,
            'monthlyTotalSales'    => $monthlyTotalSales,
            'monthlyTotalExpenses' => $monthlyTotalExpenses,
            'monthlyNetIncome'     => $monthlyNetIncome,
        ]);
    }

    public function updatePurchase(Request $request, StockMovement $movement)
    {
        $validated = $request->validate([
            'quantity'   => ['required', 'numeric', 'gt:0'],
            'total_cost' => ['required', 'numeric', 'min:0'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $qtyCol = $this->resolveMovementQtyColumn();

        DB::transaction(function () use ($validated, $movement, $qtyCol) {
            $oldQty = $qtyCol ? (float) ($movement->{$qtyCol} ?? 0) : 0.0;
            $newQty = (float) $validated['quantity'];
            $diffQty = $newQty - $oldQty;

            if ($movement->ingredient_id && abs($diffQty) > 0) {
                $ingredient = Ingredient::lockForUpdate()->find($movement->ingredient_id);
                if ($ingredient) {
                    $ingredient->current_stock = max(0, (float) $ingredient->current_stock + $diffQty);
                    $ingredient->save();
                }
            }

            if ($qtyCol) {
                $movement->{$qtyCol} = $newQty;
            }
            if (Schema::hasColumn('stock_movements', 'total_cost')) {
                $movement->total_cost = (float) $validated['total_cost'];
            }
            if (Schema::hasColumn('stock_movements', 'unit_cost') && $newQty > 0) {
                $movement->unit_cost = round((float) $validated['total_cost'] / $newQty, 2);
            }
            if (Schema::hasColumn('stock_movements', 'notes')) {
                $movement->notes = $validated['notes'];
            } elseif (Schema::hasColumn('stock_movements', 'reason')) {
                $movement->reason = $validated['notes'];
            } elseif (Schema::hasColumn('stock_movements', 'description')) {
                $movement->description = $validated['notes'];
            }

            $movement->save();
        });

        return back()->with('success', 'Catatan belanja berhasil diperbarui dan stok bahan baku disesuaikan.');
    }

    public function destroyPurchase(StockMovement $movement)
    {
        $qtyCol = $this->resolveMovementQtyColumn();

        DB::transaction(function () use ($movement, $qtyCol) {
            $oldQty = $qtyCol ? (float) ($movement->{$qtyCol} ?? 0) : 0.0;

            if ($movement->ingredient_id && $oldQty > 0) {
                $ingredient = Ingredient::lockForUpdate()->find($movement->ingredient_id);
                if ($ingredient) {
                    $ingredient->current_stock = max(0, (float) $ingredient->current_stock - $oldQty);
                    $ingredient->save();
                }
            }

            $movement->delete();
        });

        return back()->with('success', 'Catatan belanja berhasil dihapus dan saldo stok telah dikoreksi.');
    }

    private function resolveMovementQtyColumn(): ?string
    {
        return collect(['quantity_change', 'quantity', 'qty_change', 'delta', 'qty', 'amount', 'change_amount', 'stock_change'])
            ->first(fn (string $col) => Schema::hasColumn('stock_movements', $col));
    }
}