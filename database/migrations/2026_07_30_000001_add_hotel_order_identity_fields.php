<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_orders', function (Blueprint $table) {
            $table->string('order_type', 30)->default('employee')->after('service_date');
            $table->string('employee_tag_number', 100)->nullable()->after('employee_name');
            $table->string('guest_name')->nullable()->after('department_number');
            $table->string('room_number', 100)->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_type',
                'employee_tag_number',
                'guest_name',
                'room_number',
            ]);
        });
    }
};
