<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductModifier;
use App\Models\ProductRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('sort_order')->get();
        $products = Product::with(['category', 'recipes.ingredient'])->orderBy('name')->get();
        $modifiers = ProductModifier::with(['recipes.ingredient'])->orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get();

        return view('catalog.index', compact('categories', 'products', 'modifiers', 'ingredients'));
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        Product::create($validated);

        return back()->with('success', "Menu {$validated['name']} berhasil ditambahkan.");
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
        ]);

        $product->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'price' => $validated['price'],
            'is_available' => $request->boolean('is_available'),
        ]);

        return back()->with('success', "Menu {$product->name} berhasil diperbarui.");
    }

    public function storeModifier(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        ProductModifier::create($validated);

        return back()->with('success', "Topping {$validated['name']} berhasil ditambahkan.");
    }

    public function syncRecipe(Request $request)
    {
        $validated = $request->validate([
            'target_type' => ['required', 'in:product,modifier'],
            'target_id' => ['required', 'uuid'],
            'items' => ['nullable', 'array'],
            'items.*.ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'items.*.quantity_required' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($validated) {
            if ($validated['target_type'] === 'product') {
                ProductRecipe::where('product_id', $validated['target_id'])->delete();
            } else {
                ProductRecipe::where('modifier_id', $validated['target_id'])->delete();
            }

            $merged = [];
            foreach ($validated['items'] ?? [] as $row) {
                $ingId = $row['ingredient_id'];
                $merged[$ingId] = ($merged[$ingId] ?? 0) + (float) $row['quantity_required'];
            }

            foreach ($merged as $ingId => $qty) {
                ProductRecipe::create([
                    'product_id' => $validated['target_type'] === 'product' ? $validated['target_id'] : null,
                    'modifier_id' => $validated['target_type'] === 'modifier' ? $validated['target_id'] : null,
                    'ingredient_id' => $ingId,
                    'quantity_required' => $qty,
                ]);
            }
        });

        return back()->with('success', 'Komposisi resep & HPP berhasil disimpan.');
    }
}