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
        Schema::create('entrytypes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('firm_id');
            $table->unsignedInteger('folder_id');
            $table->string('name');
            $table->timestamps();
            $table->unique(['firm_id', 'folder_id', 'name']);            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrytypes');
    }
};
