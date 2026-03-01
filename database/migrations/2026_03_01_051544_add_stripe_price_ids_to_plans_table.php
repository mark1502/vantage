<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_price_monthly')->nullable()->after('stripe_product_id');
            $table->string('stripe_price_quarterly')->nullable()->after('stripe_price_monthly');
            $table->string('stripe_price_yearly')->nullable()->after('stripe_price_quarterly');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['stripe_price_monthly', 'stripe_price_quarterly', 'stripe_price_yearly']);
        });
    }
};
