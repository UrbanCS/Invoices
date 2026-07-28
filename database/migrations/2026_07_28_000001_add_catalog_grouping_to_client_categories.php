<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_categories', function (Blueprint $table) {
            $table->string('service_type', 50)->default('other')->after('name');
            $table->string('audience', 50)->default('unisex')->after('service_type');
            $table->index(
                ['client_id', 'service_type', 'audience', 'sort_order'],
                'client_categories_catalog_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('client_categories', function (Blueprint $table) {
            $table->dropIndex('client_categories_catalog_index');
            $table->dropColumn(['service_type', 'audience']);
        });
    }
};
