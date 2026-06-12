<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_categories', function (Blueprint $table) {
            $table->integer('default_price_cents')->default(0)->after('is_taxable');
        });

        Schema::create('client_employee_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['client_id', 'name'], 'client_employee_unique');
        });

        Schema::create('cleaning_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date');
            $table->string('employee_name')->nullable();
            $table->enum('status', ['submitted', 'reviewed', 'invoiced', 'cancelled'])->default('submitted');
            $table->integer('subtotal_cents')->default(0);
            $table->integer('adjustment_cents')->default(0);
            $table->text('adjustment_note')->nullable();
            $table->integer('total_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'service_date', 'status']);
        });

        Schema::create('cleaning_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name_snapshot');
            $table->integer('unit_price_cents')->default(0);
            $table->decimal('quantity', 8, 2)->default(0);
            $table->integer('total_cents')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_order_items');
        Schema::dropIfExists('cleaning_orders');
        Schema::dropIfExists('client_employee_names');

        Schema::table('client_categories', function (Blueprint $table) {
            $table->dropColumn('default_price_cents');
        });
    }
};
