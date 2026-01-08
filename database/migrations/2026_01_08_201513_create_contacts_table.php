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
        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('firm_id');
            $table->string('title', 5);
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('srjr')->nullable();
            $table->string('esqphd')->nullable();
            $table->string('company')->nullable();
            $table->string('business_title')->nullable();
            $table->string('display_name');
            $table->string('display_last_first');
            $table->string('address')->nullable();
            $table->string('email')->nullable();
            $table->string('email_alt')->nullable();
            $table->string('home_phone')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('cell_phone')->nullable();
            $table->string('fax_phone')->nullable();
            $table->string('other_phone')->nullable();
            $table->string('primary', 1)->nullable();
            $table->string('secondary', 1)->nullable();
            $table->string('note',1000)->nullable();
            $table->boolean('is_firm_member')->default(false);
            $table->unsignedInteger('user_id')->nullable();  // if a firm member, user_id
            $table->string('member_initials')->nullable();
            $table->string('firm_role')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['firm_id', 'display_name']);
            $table->unique(['firm_id', 'member_initials']);
            $table->index(['firm_id', 'display_last_first']);
            $table->index(['firm_id', 'is_firm_member', 'display_last_first']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
