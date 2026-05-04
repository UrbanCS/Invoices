<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('daily_records', function (Blueprint $table) {
            $table->string('received_by')->nullable()->after('reference_number');
            $table->string('hotel_signature')->nullable()->after('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('daily_records', function (Blueprint $table) {
            $table->dropColumn(['received_by', 'hotel_signature']);
        });
    }
};
