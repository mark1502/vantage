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
        Schema::create('filetypes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 30);
            $table->unsignedInteger('firm_id')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['firm_id', 'name']);
            $table->boolean('has_correspondence')->default(true);
            $table->boolean('has_pleadings')->default(true);
            $table->boolean('has_discovery')->default(true);
            $table->boolean('has_documents')->default(true);
            $table->boolean('has_memos')->default(true);
            $table->boolean('has_events')->default(true);
            $table->boolean('has_todo')->default(true);
            $table->boolean('has_phone')->default(true);
            $table->boolean('has_medrecs')->default(true);
            $table->boolean('has_medbills')->default(true);
            $table->boolean('has_costs')->default(true);
            $table->boolean('enable_file_SOL')->default(true);
            $table->boolean('set_as_default')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filetypes');
    }
};
