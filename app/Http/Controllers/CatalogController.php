<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductModifier;
use App\Models\ProductRecipe;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogController extends Controller
{
    public function index()
    {
        $costCol = collect(['cost_per_unit', 'average_cost', 'purchase_price', 'unit_cost'])
            ->first(fn (string $col) => Schema::hasColumn('ingredients', $col));

        $recipeQtyCol = $this->resolveRecipeQtyColumn();

        $categories = Category::orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get()->map(function ($ing) use ($costCol) {
            $ing->resolved_cost = $costCol ? (float) $ing->{$costCol} : 0.0;
            return $ing;
        });

        $products = Product::with(['category', 'recipes.ingredient'])
            ->orderBy('name')
            ->get()
            ->map(function ($prod) use ($costCol, $recipeQtyCol) {
                $hpp = 0.0;
                $recipeItems = [];

                foreach ($prod->recipes as $rec) {
                    $qty = $recipeQtyCol ? (float) ($rec->{$recipeQtyCol} ?? 0) : 0.0;
                    $unitCost = $costCol ? (float) ($rec->ingredient?->{$costCol} ?? 0) : 0.0;
                    $hpp += $qty * $unitCost;

                    $recipeItems[] = [
                        'ingredient_id' => $rec->ingredient_id,
                        'quantity'      => $qty,
                    ];
                }

                $prod->estimated_hpp = round($hpp, 2);
                $prod->recipe_items_json = $recipeItems;
                return $prod;
            });

        $modifiers = ProductModifier::orderBy('name')->get();

        return view('catalog.index', [
            'categories'  => $categories,
            'products'    => $products,
            'ingredients' => $ingredients,
            'modifiers'   => $modifiers,
        ]);
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'category_id'  => ['required', 'exists:categories,id'],
            'name'         => ['required', 'string', 'max:120'],
            'price'        => ['required', 'numeric', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'category_id' => $validated['category_id'],
            'name'        => $validated['name'],
            'price'       => $validated['price'],
        ];

        if (Schema::hasColumn('products', 'is_available')) {
            $payload['is_available'] = $request->boolean('is_available', true);
        } elseif (Schema::hasColumn('products', 'is_active')) {
            $payload['is_active'] = $request->boolean('is_available', true);
        }

        Product::create($payload);

        return back()->with('success', "Menu {$validated['name']} berhasil ditambahkan.");
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id'  => ['required', 'exists:categories,id'],
            'name'         => ['required', 'string', 'max:120'],
            'price'        => ['required', 'numeric', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'category_id' => $validated['category_id'],
            'name'        => $validated['name'],
            'price'       => $validated['price'],
        ];

        if (Schema::hasColumn('products', 'is_available')) {
            $payload['is_available'] = $request->boolean('is_available', false);
        } elseif (Schema::hasColumn('products', 'is_active')) {
            $payload['is_active'] = $request->boolean('is_available', false);
        }

        $product->update($payload);

        return back()->with('success', "Menu {$product->name} berhasil diperbarui.");
    }

    public function destroyProduct(Product $product)
    {
        $name = $product->name;
        try {
            DB::transaction(function () use ($product) {
                ProductRecipe::where('product_id', $product->id)->delete();
                $product->delete();
            });
            return back()->with('success', "Menu {$name} berhasil dihapus.");
        } catch (QueryException $e) {
            return back()->withErrors([
                'product' => "Menu {$name} tidak dapat dihapus karena sudah tercatat pada riwayat transaksi. Gunakan tombol Edit untuk menonaktifkannya.",
            ]);
        }
    }

    public function storeModifier(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $payload = ['name' => $validated['name']];
        if (Schema::hasColumn('product_modifiers', 'price')) {
            $payload['price'] = $validated['price'];
        } elseif (Schema::hasColumn('product_modifiers', 'additional_price')) {
            $payload['additional_price'] = $validated['price'];
        }

        ProductModifier::create($payload);

        return back()->with('success', "Topping {$validated['name']} berhasil ditambahkan.");
    }

    public function updateModifier(Request $request, ProductModifier $modifier)
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $modifier->name = $validated['name'];
        if (Schema::hasColumn('product_modifiers', 'price')) {
            $modifier->price = $validated['price'];
        } elseif (Schema::hasColumn('product_modifiers', 'additional_price')) {
            $modifier->additional_price = $validated['price'];
        }
        $modifier->save();

        return back()->with('success', "Topping {$modifier->name} berhasil diperbarui.");
    }

    public function destroyModifier(ProductModifier $modifier)
    {
        $name = $modifier->name;
        try {
            $modifier->delete();
            return back()->with('success', "Topping {$name} berhasil dihapus.");
        } catch (QueryException $e) {
            return back()->withErrors([
                'modifier' => "Topping {$name} tidak dapat dihapus karena sedang digunakan.",
            ]);
        }
    }

    public function syncRecipe(Request $request)
    {
        $validated = $request->validate([
            'product_id'            => ['required', 'exists:products,id'],
            'items'                 => ['nullable', 'array'],
            'items.*.ingredient_id' => ['required', 'exists:ingredients,id'],
            'items.*.quantity'      => ['required', 'numeric', 'gt:0'],
        ]);

        $recipeQtyCol = $this->resolveRecipeQtyColumn();

        DB::transaction(function () use ($validated, $recipeQtyCol) {
            ProductRecipe::where('product_id', $validated['product_id'])->delete();

            foreach ($validated['items'] ?? [] as $row) {
                ProductRecipe::create([
                    'product_id'    => $validated['product_id'],
                    'ingredient_id' => $row['ingredient_id'],
                    $recipeQtyCol   => $row['quantity'],
                ]);
            }
        });

        return back()->with('success', 'Takaran resep bahan baku (BOM) berhasil disimpan.');
    }

    private function resolveRecipeQtyColumn(): string
    {
        return collect(['quantity_required', 'quantity', 'qty_required', 'amount'])
            ->first(fn (string $col) => Schema::hasColumn('product_recipes', $col)) ?? 'quantity_required';
    }
}