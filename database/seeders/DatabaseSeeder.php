<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductModifier;
use App\Models\ProductRecipe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Pengguna (Owner & Kasir)
        User::create([
            'name' => 'Pak Hendra',
            'username' => 'owner',
            'password' => Hash::make('permata123'),
            'role' => 'OWNER',
        ]);

        User::create([
            'name' => 'Rian (Kasir)',
            'username' => 'kasir',
            'password' => Hash::make('kasir123'),
            'role' => 'KASIR',
        ]);

        // 2. Kategori Menu
        $catMakanan = Category::create(['name' => 'Makanan & Mie', 'sort_order' => 1]);
        $catMinuman = Category::create(['name' => 'Minuman', 'sort_order' => 2]);

        // 3. Bahan Baku Utama
        $mieGoreng = Ingredient::create(['name' => 'Indomie Goreng Bungkus', 'unit' => 'bungkus', 'current_stock' => 120, 'avg_cost_per_unit' => 2800, 'min_stock_alert' => 20]);
        $mieSoto   = Ingredient::create(['name' => 'Indomie Kuah Soto Bungkus', 'unit' => 'bungkus', 'current_stock' => 80, 'avg_cost_per_unit' => 2700, 'min_stock_alert' => 15]);
        $telur     = Ingredient::create(['name' => 'Telur Ayam', 'unit' => 'butir', 'current_stock' => 90, 'avg_cost_per_unit' => 1800, 'min_stock_alert' => 15]);
        $sawi      = Ingredient::create(['name' => 'Sawi Hijau', 'unit' => 'g', 'current_stock' => 2000, 'avg_cost_per_unit' => 15, 'min_stock_alert' => 300]);
        $kornet    = Ingredient::create(['name' => 'Kornet Sapi', 'unit' => 'g', 'current_stock' => 1500, 'avg_cost_per_unit' => 65, 'min_stock_alert' => 250]);
        $tehCelup  = Ingredient::create(['name' => 'Teh Celup', 'unit' => 'pcs', 'current_stock' => 100, 'avg_cost_per_unit' => 350, 'min_stock_alert' => 20]);
        $gula      = Ingredient::create(['name' => 'Gula Pasir', 'unit' => 'g', 'current_stock' => 5000, 'avg_cost_per_unit' => 18, 'min_stock_alert' => 500]);

        // 4. Produk Menu & Resepnya
        $pIndomieGoreng = Product::create(['category_id' => $catMakanan->id, 'name' => 'Indomie Goreng Original', 'price' => 8000]);
        ProductRecipe::create(['product_id' => $pIndomieGoreng->id, 'ingredient_id' => $mieGoreng->id, 'quantity_required' => 1]);
        ProductRecipe::create(['product_id' => $pIndomieGoreng->id, 'ingredient_id' => $sawi->id, 'quantity_required' => 20]);

        $pIndomieSoto = Product::create(['category_id' => $catMakanan->id, 'name' => 'Indomie Kuah Soto', 'price' => 8000]);
        ProductRecipe::create(['product_id' => $pIndomieSoto->id, 'ingredient_id' => $mieSoto->id, 'quantity_required' => 1]);
        ProductRecipe::create(['product_id' => $pIndomieSoto->id, 'ingredient_id' => $sawi->id, 'quantity_required' => 20]);

        $pEsTeh = Product::create(['category_id' => $catMinuman->id, 'name' => 'Es Teh Manis', 'price' => 4000]);
        ProductRecipe::create(['product_id' => $pEsTeh->id, 'ingredient_id' => $tehCelup->id, 'quantity_required' => 1]);
        ProductRecipe::create(['product_id' => $pEsTeh->id, 'ingredient_id' => $gula->id, 'quantity_required' => 25]);

        // 5. Topping / Modifier & Resepnya
        $modTelur = ProductModifier::create(['name' => 'Telur Ceplok / Dadar', 'price' => 4000]);
        ProductRecipe::create(['modifier_id' => $modTelur->id, 'ingredient_id' => $telur->id, 'quantity_required' => 1]);

        $modKornet = ProductModifier::create(['name' => 'Ekstra Kornet', 'price' => 5000]);
        ProductRecipe::create(['modifier_id' => $modKornet->id, 'ingredient_id' => $kornet->id, 'quantity_required' => 35]);
    }
}