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
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('firm_id');
            $table->unsignedInteger('file_id');
            $table->unsignedInteger('folder_id');
            $table->unsignedInteger('entrytype_id');
            $table->unsignedInteger('from_contact_id');  // person from
            $table->unsignedInteger('to_contact_id')->nullable();  // person to
            $table->dateTime('date1');
            $table->dateTime('date2')->nullable();
            $table->string('note', 5000)->nullable();
            $table->dateTime('date_response_expected')->nullable();  // using datetime for calendar use?
            $table->boolean('expecting_response')->default(false);
            $table->boolean('on_calendar')->default(false);
            $table->boolean('all_day')->default(false);
            $table->decimal('amount', 7, 2)->nullable();
            $table->string('linked_document_path', 500)->nullable();
            $table->timestamps();
            // $table->softDeletes();  --> Don't soft delete entries
            $table->index(['file_id', 'folder_id', 'date1']);                             // file, folder, date
            $table->index(['file_id', 'date1']);                                          // file, date
            $table->index(['file_id', 'expecting_response']);                             // file, expecting_response
            $table->index(['firm_id', 'date1']);                                          // firm, date
            $table->index(['firm_id', 'expecting_response', 'date_response_expected']);   // firm, expecting_response, date_response_expected
            $table->index(['firm_id', 'on_calendar', 'date_response_expected']);          // firm, on_calendar, date_response_expected
            $table->index(['firm_id', 'folder_id', 'date2']);                             // firm, folder, date2
            $table->index('from_contact_id');
            $table->index('to_contact_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
