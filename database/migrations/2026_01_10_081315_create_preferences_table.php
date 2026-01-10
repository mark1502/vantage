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
        Schema::create('preferences', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pref_default_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('firm_id');
            $table->string('name');
            $table->string('prompt');
            $table->string('setting');
            $table->timestamps();
            $table->index(['user_id', 'name']);
            $table->index(['firm_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferences');
    }
};
