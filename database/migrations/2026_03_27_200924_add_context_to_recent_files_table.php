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
        Schema::table('recent_files', function (Blueprint $table) {
            $table->string('filepart')->default('correspondence')->after('last_opened_at');
            $table->unsignedInteger('page')->default(1)->after('filepart');
            $table->unsignedInteger('show')->default(15)->after('page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recent_files', function (Blueprint $table) {
            $table->dropColumn(['filepart', 'page', 'show']);
        });
    }
};
