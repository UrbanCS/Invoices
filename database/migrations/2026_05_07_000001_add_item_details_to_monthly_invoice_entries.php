<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('monthly_invoice_entries', function (Blueprint $table) {
            $table->json('item_details')->nullable()->after('amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_invoice_entries', function (Blueprint $table) {
            $table->dropColumn('item_details');
        });
    }
};
