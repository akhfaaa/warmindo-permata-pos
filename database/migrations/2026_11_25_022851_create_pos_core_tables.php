<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Kategori Menu
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Produk / Menu Utama
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        // 3. Topping / Varian Tambahan
        Schema::create('product_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Bahan Baku Mentah
        Schema::create('ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('unit', 20); // g, ml, pcs, butir, bungkus
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('avg_cost_per_unit', 12, 2)->default(0);
            $table->decimal('min_stock_alert', 12, 2)->default(0);
            $table->timestamps();
        });

        // 5. Resep / Bill of Materials (Menu maupun Topping)
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('modifier_id')->nullable()->constrained('product_modifiers')->cascadeOnDelete();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity_required', 12, 2);
            $table->timestamps();
        });

        // 6. Shift Kasir & Tutup Laci
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('actual_cash', 12, 2)->nullable();
            $table->decimal('discrepancy', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->timestamps();
        });

        // 7. Transaksi Penjualan
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Digunakan juga sebagai idempotency key offline sync
            $table->string('invoice_number')->unique();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('shift_id')->nullable()->constrained('cash_shifts')->restrictOnDelete();
            $table->enum('order_type', ['DINE_IN', 'TAKEAWAY'])->default('DINE_IN');
            $table->enum('payment_method', ['TUNAI', 'QRIS']);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('cash_received', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->enum('status', ['PAID', 'VOID'])->default('PAID');
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->boolean('is_offline_synced')->default(false);
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });

        // 8. Detail Item Transaksi (dengan Snapshot HPP)
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->json('modifiers_json')->nullable();
            $table->decimal('item_hpp_snapshot', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // 9. Mutasi Pergerakan Stok (Ledger)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->enum('type', ['IN', 'OUT', 'ADJ']);
            $table->decimal('quantity_change', 12, 2); // Positif untuk masuk, negatif untuk keluar
            $table->decimal('cost_per_unit', 12, 2)->nullable();
            $table->uuid('reference_id')->nullable()->index(); // ID transaksi atau null jika restock/opname
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('cash_shifts');
        Schema::dropIfExists('product_recipes');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('product_modifiers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};