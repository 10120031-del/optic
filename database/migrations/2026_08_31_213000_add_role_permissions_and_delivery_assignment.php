<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('customer', 'owner', 'staff', 'delivery', 'admin') NOT NULL DEFAULT 'customer'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('customer')->change();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('assigned_delivery_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        DB::table('users')->whereIn('role', ['admin', 'owner', 'staff', 'delivery'])->update([
            'role' => DB::raw("CASE WHEN role = 'admin' THEN 'owner' ELSE role END"),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_delivery_user_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['customer', 'admin'])->default('customer')->change();
            });
        }
    }
};
