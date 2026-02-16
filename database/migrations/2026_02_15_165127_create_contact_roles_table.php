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
        Schema::create('contact_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('file_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('role_id')->nullable();
            $table->boolean('is_client')->default(false);
            $table->boolean('is_attorney')->default(false);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');

            // Prevent duplicate contact assignments to same file
            $table->unique(['file_id', 'contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_roles');
    }
};
