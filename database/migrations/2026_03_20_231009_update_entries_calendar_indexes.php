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
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex(['firm_id', 'on_calendar', 'date_response_expected']);    // no longer used by get_events query
            $table->dropIndex(['firm_id', 'folder_id', 'date2']);                       // not used by any query

            $table->index(['firm_id', 'folder_id', 'date1']);                           // supports new calendar event query (folder_id=6 + date1 range)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex(['firm_id', 'folder_id', 'date1']);

            $table->index(['firm_id', 'on_calendar', 'date_response_expected']);
            $table->index(['firm_id', 'folder_id', 'date2']);
        });
    }
};
