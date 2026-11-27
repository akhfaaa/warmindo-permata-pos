<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(30) NOT NULL DEFAULT 'CREW'");
        }

        DB::table('users')->where('role', 'OWNER')->update(['role' => 'ADMIN_UTAMA']);
        DB::table('users')->where('role', 'KASIR')->update(['role' => 'CREW']);

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'allowed_menus')) {
                $table->json('allowed_menus')->nullable()->after('role');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('allowed_menus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(['allowed_menus', 'is_active'], fn ($c) => Schema::hasColumn('users', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};