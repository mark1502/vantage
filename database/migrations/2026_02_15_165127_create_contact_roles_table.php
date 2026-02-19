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
            $table->string('role');
            $table->string('role_label')->nullable();
            $table->boolean('is_protected')->default(false);
            $table->boolean('is_file_attorney')->default(false);
            $table->boolean('is_file_client')->default(false);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('file_id')->references('id')->on('files')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');

            // A contact can hold multiple different roles on a file
            $table->unique(['file_id', 'contact_id', 'role']);
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
