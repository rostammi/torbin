<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('contract_amount')->nullable()->after('priority');
            $table->string('contract_currency')->default('تومان')->after('contract_amount');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropColumn(['contract_amount', 'contract_currency']);
        });
    }
};
