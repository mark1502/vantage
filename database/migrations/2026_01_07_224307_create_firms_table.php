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
        Schema::create('firms', function (Blueprint $table) {
            $table->increments('id');   // unsignedInteger with auto-increment
            $table->string('name');     // allow duplicate firm names (multi state), but make the email for the firm unique
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();  // each firm has a unique email
            $table->string('document_base_path', 500)->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('billing_contact_id')->nullable();
            $table->timestamps();

            $table->foreign('billing_contact_id')->references('id')->on('contacts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firms');
    }
};
