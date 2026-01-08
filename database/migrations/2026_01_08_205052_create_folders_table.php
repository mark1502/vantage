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
        Schema::create('folders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->boolean('input_time')->default(false);
            $table->string('date1_prompt', 20)->default('Date:');
            $table->string('date2_prompt', 20)->nullable();
            $table->boolean('hide_date2_prompt')->default(false);
            $table->string('from_prompt')->nullable();
            $table->string('to_prompt')->nullable();
            $table->boolean('hide_to_prompt')->default(false);
            $table->string('entrytype_prompt')->nullable();
            $table->boolean('hide_entrytype_prompt')->default(false);
            $table->string('note_prompt')->nullable();
            $table->string('responseexpected_prompt')->nullable();
            $table->boolean('hide_responseexpected_prompt')->default(false);
            $table->boolean('hide_isResponsive')->default(false);
            $table->string('amount_prompt')->nullable();
            $table->boolean('hide_amount_prompt')->default(false);
            $table->string('short_name')->nullable();
            $table->timestamps();
            $table->unique('short_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
