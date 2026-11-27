<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryController extends Controller
{
    public function index()
    {
        $minCol = $this->resolveMinStockColumn();
        $costCol = $this->resolveCostColumn();
        $qtyCol = $this->resolveMovementQtyColumn();

        $ingredients = Ingredient::orderBy('name')->get()->map(function ($ing) use ($minCol, $costCol) {
            $ing->resolved_min_stock = $minCol ? (float) $ing->{$minCol} : 0.0;
            $ing->resolved_cost = $costCol ? (float) $ing->{$costCol} : 0.0;
            return $ing;
        });

        $lowStockCount = $ingredients->filter(fn ($i) => (float) $i->current_stock <= $i->resolved_min_stock)->count();

        $relations = ['ingredient'];
        if (Schema::hasColumn('stock_movements', 'user_id')) {
            $relations[] = 'user';
        }

        $movements = StockMovement::with($relations)
            ->latest('created_at')
            ->limit(25)
            ->get()
            ->map(function ($mov) use ($qtyCol) {
                $mov->resolved_qty = $qtyCol ? (float) ($mov->{$qtyCol} ?? 0) : 0.0;
                return $mov;
            });

        return view('inventory.index', [
            'ingredients'   => $ingredients,
            'lowStockCount' => $lowStockCount,
            'movements'     => $movements,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'unit'          => ['required', 'string', 'max:30'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'min_stock'     => ['required', 'numeric', 'min:0'],
            'cost_per_unit' => ['required', 'numeric', 'min:0'],
        ]);

        $minCol = $this->resolveMinStockColumn();
        $costCol = $this->resolveCostColumn();

        DB::transaction(function () use ($validated, $minCol, $costCol, $request) {
            $data = [
                'name'          => $validated['name'],
                'unit'          => $validated['unit'],
                'current_stock' => $validated['current_stock'],
            ];

            if ($minCol) {
                $data[$minCol] = $validated['min_stock'];
            }
            if ($costCol) {
                $data[$costCol] = $validated['cost_per_unit'];
            }

            $ingredient = Ingredient::create($data);

            if ((float) $validated['current_stock'] > 0) {
                $totalCost = (float) $validated['current_stock'] * (float) $validated['cost_per_unit'];
                $this->recordMovement(
                    $ingredient->id,
                    'RESTOCK',
                    (float) $validated['current_stock'],
                    $totalCost,
                    'Stok awal bahan baku baru',
                    (string) $request->user()->id
                );
            }
        });

        return back()->with('success', "Bahan baku {$validated['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'unit'          => ['required', 'string', 'max:30'],
            'min_stock'     => ['required', 'numeric', 'min:0'],
            'cost_per_unit' => ['required', 'numeric', 'min:0'],
        ]);

        $minCol = $this->resolveMinStockColumn();
        $costCol = $this->resolveCostColumn();

        $ingredient->name = $validated['name'];
        $ingredient->unit = $validated['unit'];
        if ($minCol) {
            $ingredient->{$minCol} = $validated['min_stock'];
        }
        if ($costCol) {
            $ingredient->{$costCol} = $validated['cost_per_unit'];
        }
        $ingredient->save();

        return back()->with('success', "Data bahan baku {$ingredient->name} berhasil diperbarui.");
    }

    public function destroy(Ingredient $ingredient)
    {
        $name = $ingredient->name;
        try {
            $ingredient->delete();
            return back()->with('success', "Bahan baku {$name} berhasil dihapus.");
        } catch (QueryException $e) {
            return back()->withErrors([
                'ingredient' => "Bahan baku {$name} tidak dapat dihapus karena sudah terhubung dengan resep menu atau riwayat stok.",
            ]);
        }
    }

    public function restock(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'quantity'      => ['required', 'numeric', 'gt:0'],
            'total_cost'    => ['required', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string', 'max:255'],
        ]);

        $costCol = $this->resolveCostColumn();

        DB::transaction(function () use ($validated, $costCol, $request) {
            $ingredient = Ingredient::lockForUpdate()->findOrFail($validated['ingredient_id']);

            $oldStock  = (float) $ingredient->current_stock;
            $addQty    = (float) $validated['quantity'];
            $totalCost = (float) $validated['total_cost'];
            $newStock  = $oldStock + $addQty;

            if ($costCol && $addQty > 0 && $totalCost > 0) {
                $oldCost = (float) $ingredient->{$costCol};
                $newUnitCost = (($oldStock * $oldCost) + $totalCost) / $newStock;
                $ingredient->{$costCol} = round($newUnitCost, 2);
            }

            $ingredient->current_stock = $newStock;
            $ingredient->save();

            $this->recordMovement(
                $ingredient->id,
                'RESTOCK',
                $addQty,
                $totalCost,
                $validated['notes'] ?: "Belanja / Restock {$ingredient->name}",
                (string) $request->user()->id
            );
        });

        return back()->with('success', 'Restock bahan baku & catatan belanja berhasil disimpan.');
    }

    public function opname(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => ['required', 'exists:ingredients,id'],
            'actual_stock'  => ['required', 'numeric', 'min:0'],
            'notes'         => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $ingredient  = Ingredient::lockForUpdate()->findOrFail($validated['ingredient_id']);
            $systemStock = (float) $ingredient->current_stock;
            $actualStock = (float) $validated['actual_stock'];
            $diff        = $actualStock - $systemStock;

            $ingredient->current_stock = $actualStock;
            $ingredient->save();

            $this->recordMovement(
                $ingredient->id,
                'OPNAME',
                $diff,
                0,
                "Stock Opname: {$validated['notes']}",
                (string) $request->user()->id
            );
        });

        return back()->with('success', 'Penyesuaian stok fisik (Stock Opname) berhasil dicatat.');
    }

    private function recordMovement(string $ingredientId, string $desiredType, float $qty, float $totalCost, string $notes, string $userId): void
    {
        $payload = [
            'ingredient_id' => $ingredientId,
        ];

        if (Schema::hasColumn('stock_movements', 'user_id')) {
            $payload['user_id'] = $userId;
        } elseif (Schema::hasColumn('stock_movements', 'created_by')) {
            $payload['created_by'] = $userId;
        }

        if (Schema::hasColumn('stock_movements', 'type')) {
            $payload['type'] = $this->resolveAllowedMovementType($desiredType, $qty);
        }

        $qtyCol = $this->resolveMovementQtyColumn();
        if ($qtyCol) {
            $payload[$qtyCol] = $qty;
        }

        if (Schema::hasColumn('stock_movements', 'total_cost')) {
            $payload['total_cost'] = $totalCost;
        }
        if (Schema::hasColumn('stock_movements', 'unit_cost') && abs($qty) > 0) {
            $payload['unit_cost'] = round($totalCost / abs($qty), 2);
        }

        if (Schema::hasColumn('stock_movements', 'notes')) {
            $payload['notes'] = $notes;
        } elseif (Schema::hasColumn('stock_movements', 'reason')) {
            $payload['reason'] = $notes;
        } elseif (Schema::hasColumn('stock_movements', 'description')) {
            $payload['description'] = $notes;
        }

        StockMovement::create($payload);
    }

    private function resolveAllowedMovementType(string $desiredType, float $qty): string
    {
        try {
            $colInfo = DB::selectOne("SHOW COLUMNS FROM stock_movements WHERE Field = 'type'");
            $rawType = $colInfo->Type ?? '';

            if (str_starts_with(strtolower($rawType), 'enum(')) {
                preg_match_all("/'([^']+)'/", $rawType, $matches);
                $allowed = $matches[1] ?? [];

                if (! empty($allowed)) {
                    $preferred = $desiredType === 'RESTOCK'
                        ? ['RESTOCK', 'IN', 'PURCHASE', 'STOCK_IN']
                        : ['OPNAME', 'ADJUSTMENT', $qty >= 0 ? 'IN' : 'OUT'];

                    foreach ($preferred as $candidate) {
                        if (in_array($candidate, $allowed, true)) {
                            return $candidate;
                        }
                    }

                    return $allowed[0];
                }
            }
        } catch (\Throwable $e) {
        }

        return $desiredType;
    }

    private function resolveMovementQtyColumn(): ?string
    {
        $candidates = ['quantity_change', 'quantity', 'qty_change', 'delta', 'qty', 'amount', 'change_amount', 'stock_change'];
        $found = collect($candidates)->first(fn (string $col) => Schema::hasColumn('stock_movements', $col));

        if ($found) {
            return $found;
        }

        $excluded = [
            'id', 'ingredient_id', 'user_id', 'created_by', 'type', 'total_cost',
            'unit_cost', 'notes', 'reason', 'description', 'reference_id', 'created_at', 'updated_at',
        ];

        return collect(Schema::getColumnListing('stock_movements'))
            ->first(fn (string $col) => ! in_array($col, $excluded, true));
    }

    private function resolveMinStockColumn(): ?string
    {
        return collect(['min_stock', 'minimum_stock', 'min_stock_threshold', 'stock_threshold'])
            ->first(fn (string $col) => Schema::hasColumn('ingredients', $col));
    }

    private function resolveCostColumn(): ?string
    {
        return collect(['cost_per_unit', 'average_cost', 'purchase_price', 'unit_cost'])
            ->first(fn (string $col) => Schema::hasColumn('ingredients', $col));
    }
}