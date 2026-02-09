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
        Schema::create('files', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('summary',5000)->nullable();
            $table->date('date_sol')->nullable();
            $table->date('date_opened')->nullable();
            $table->date('date_filed')->nullable();
            $table->date('date_closed')->nullable();
            $table->date('date_archived')->nullable();
            $table->string('court_filed')->nullable();
            $table->string('docket_number')->nullable();
            $table->string('file_number')->nullable();
            $table->string('referred_by')->nullable();
            $table->string('referral_amount')->nullable();
            $table->string('fee_arrangement')->nullable();
            $table->string('fee_amount')->nullable();
            $table->string('final_disposition')->nullable();
            $table->unsignedInteger('filetype_id')->nullable(); // if for the filetype
            $table->unsignedInteger('contact_id');              // assigned_atty - firm member in contacts file
            $table->unsignedInteger('firm_id');
            $table->timestamps();
            // $table->softDeletes(); No more softDeletes, filter on closed date instead
            $table->unique(['firm_id', 'name']);
            $table->index(['firm_id', 'contact_id']);
            $table->index(['firm_id', 'date_opened']);
            $table->index(['firm_id', 'date_closed']);
            $table->index(['firm_id', 'date_sol']);
            $table->index(['firm_id', 'date_filed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
