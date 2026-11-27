<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'total_cost')) {
                $table->decimal('total_cost', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('stock_movements', 'notes')) {
                $table->string('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $cols = array_filter(['total_cost', 'notes'], fn ($c) => Schema::hasColumn('stock_movements', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};