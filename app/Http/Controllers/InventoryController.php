<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');

        $query = Ingredient::query()->orderBy('name');

        if ($filter === 'low') {
            $query->whereColumn('current_stock', '<=', 'min_stock_alert')
                  ->where('current_stock', '>', 0);
        } elseif ($filter === 'critical') {
            $query->where('current_stock', '<=', 0);
        }

        $ingredients = $query->get();
        $recentMovements = StockMovement::with('ingredient')
            ->latest()
            ->limit(15)
            ->get();

        return view('inventory.index', compact('ingredients', 'recentMovements', 'filter'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:20'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'avg_cost_per_unit' => ['required', 'numeric', 'min:0'],
            'min_stock_alert' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            $ingredient = Ingredient::create($validated);

            if ((float) $validated['current_stock'] > 0) {
                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'type' => 'IN',
                    'quantity_change' => $validated['current_stock'],
                    'cost_per_unit' => $validated['avg_cost_per_unit'],
                    'notes' => 'Saldo awal bahan baku baru',
                ]);
            }
        });

        return back()->with('success', "Bahan baku {$validated['name']} berhasil ditambahkan.");
    }

    public function restock(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'quantity_added' => ['required', 'numeric', 'gt:0'],
            'total_purchase_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            $ingredient = Ingredient::lockForUpdate()->findOrFail($validated['ingredient_id']);

            $qtyAdded = (float) $validated['quantity_added'];
            $totalPrice = (float) $validated['total_purchase_price'];
            $unitCostNew = $totalPrice / $qtyAdded;

            $currentStock = (float) $ingredient->current_stock;
            $currentAvgCost = (float) $ingredient->avg_cost_per_unit;

            // Hitung Moving Average Cost; jika stok lama <= 0, pakai harga beli baru langsung
            if ($currentStock <= 0) {
                $newAvgCost = $unitCostNew;
            } else {
                $totalValuation = ($currentStock * $currentAvgCost) + $totalPrice;
                $newAvgCost = $totalValuation / ($currentStock + $qtyAdded);
            }

            $ingredient->update([
                'current_stock' => $currentStock + $qtyAdded,
                'avg_cost_per_unit' => round($newAvgCost, 2),
            ]);

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => 'IN',
                'quantity_change' => $qtyAdded,
                'cost_per_unit' => round($unitCostNew, 2),
                'notes' => $validated['notes'] ?: 'Belanja / Restock bahan baku',
            ]);
        });

        return back()->with('success', 'Restock berhasil dicatat dan harga pokok rata-rata diperbarui.');
    }

    public function opname(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'actual_stock' => ['required', 'numeric', 'min:0'],
            'notes' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated) {
            $ingredient = Ingredient::lockForUpdate()->findOrFail($validated['ingredient_id']);
            $actualStock = (float) $validated['actual_stock'];
            $diff = $actualStock - (float) $ingredient->current_stock;

            $ingredient->update([
                'current_stock' => $actualStock,
            ]);

            StockMovement::create([
                'ingredient_id' => $ingredient->id,
                'type' => 'ADJ',
                'quantity_change' => $diff,
                'cost_per_unit' => $ingredient->avg_cost_per_unit,
                'notes' => 'Stock Opname: ' . $validated['notes'],
            ]);
        });

        return back()->with('success', 'Penyesuaian stok fisik (Stock Opname) berhasil disimpan.');
    }
}