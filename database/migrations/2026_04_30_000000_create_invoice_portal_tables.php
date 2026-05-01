<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('qst_number')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('default_payment_instructions')->nullable();
            $table->text('default_thank_you_message')->nullable();
            $table->enum('default_language', ['fr', 'en'])->default('fr');
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->enum('tax_profile', ['qc_tps_tvq', 'on_hst', 'custom'])->default('on_hst');
            $table->enum('default_language', ['fr', 'en'])->default('en');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });

        Schema::create('client_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('profile_key');
            $table->string('label');
            $table->integer('rate_basis_points');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('daily_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('service_date');
            $table->enum('status', ['draft', 'reviewed', 'invoiced'])->default('draft');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'service_date', 'status']);
        });

        Schema::create('daily_record_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_category_id')->constrained()->restrictOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('department_or_room')->nullable();
            $table->text('description')->nullable();
            $table->integer('amount_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('monthly_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->unsignedTinyInteger('invoice_month');
            $table->unsignedSmallInteger('invoice_year');
            $table->date('invoice_date');
            $table->enum('status', ['draft', 'approved', 'sent', 'paid', 'cancelled'])->default('draft');
            $table->enum('source_mode', ['daily_records', 'manual_grid']);
            $table->integer('subtotal_cents')->default(0);
            $table->integer('discount_cents')->default(0);
            $table->integer('taxable_subtotal_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('gst_cents')->nullable();
            $table->integer('qst_cents')->nullable();
            $table->integer('total_cents')->default(0);
            $table->integer('grand_total_cents')->default(0);
            $table->json('tax_profile_snapshot')->nullable();
            $table->json('category_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->text('thank_you_message')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'invoice_year', 'invoice_month', 'status'], 'mi_client_period_status_idx');
        });

        Schema::create('monthly_invoice_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('service_day');
            $table->foreignId('client_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name_snapshot');
            $table->integer('amount_cents')->default(0);
            $table->enum('source_type', ['manual_monthly_grid', 'daily_record'])->default('manual_monthly_grid');
            $table->timestamps();
            $table->index(['monthly_invoice_id', 'service_day']);
        });

        Schema::create('monthly_invoice_daily_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_record_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['monthly_invoice_id', 'daily_record_id'], 'invoice_daily_unique');
        });

        Schema::create('invoice_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->enum('type', ['discount', 'credit', 'fee']);
            $table->integer('amount_cents');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_invoice_id')->constrained()->cascadeOnDelete();
            $table->integer('amount_cents');
            $table->timestamp('paid_at')->nullable();
            $table->string('method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('uploaded_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('monthly_invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('daily_record_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('uploaded_documents');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_adjustments');
        Schema::dropIfExists('monthly_invoice_daily_record');
        Schema::dropIfExists('monthly_invoice_entries');
        Schema::dropIfExists('monthly_invoices');
        Schema::dropIfExists('daily_record_items');
        Schema::dropIfExists('daily_records');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('client_categories');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['client_id']));
        Schema::dropIfExists('clients');
        Schema::dropIfExists('business_settings');
    }
};
