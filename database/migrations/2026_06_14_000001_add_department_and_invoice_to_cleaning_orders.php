<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cleaning_orders', function (Blueprint $table) {
            $table->string('department_number')->nullable()->after('employee_name');
            $table->foreignId('monthly_invoice_id')
                ->nullable()
                ->after('user_id')
                ->constrained('monthly_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('monthly_invoice_id');
            $table->dropColumn('department_number');
        });
    }
};
