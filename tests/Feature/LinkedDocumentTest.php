<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Firm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_firms_table_has_document_base_path_column(): void
    {
        $firm = Firm::factory()->create([
            'document_base_path' => 'S:\\Cases',
        ]);

        $this->assertDatabaseHas('firms', [
            'id' => $firm->id,
            'document_base_path' => 'S:\\Cases',
        ]);
    }

    public function test_firms_document_base_path_is_nullable(): void
    {
        $firm = Firm::factory()->create([
            'document_base_path' => null,
        ]);

        $this->assertNull($firm->fresh()->document_base_path);
    }

    public function test_firm_has_document_base_path_helper(): void
    {
        $firmWith = Firm::factory()->create(['document_base_path' => 'S:\\Cases']);
        $firmWithout = Firm::factory()->create(['document_base_path' => null]);

        $this->assertTrue($firmWith->hasDocumentBasePath());
        $this->assertFalse($firmWithout->hasDocumentBasePath());
    }

    public function test_entries_table_has_linked_document_path_column(): void
    {
        $firm = Firm::factory()->create();
        $entry = Entry::factory()->create([
            'firm_id' => $firm->id,
            'linked_document_path' => '2024\\Smith\\contract.pdf',
        ]);

        $this->assertDatabaseHas('entries', [
            'id' => $entry->id,
            'linked_document_path' => '2024\\Smith\\contract.pdf',
        ]);
    }

    public function test_entries_linked_document_path_is_nullable(): void
    {
        $entry = Entry::factory()->create([
            'linked_document_path' => null,
        ]);

        $this->assertNull($entry->fresh()->linked_document_path);
    }

    public function test_entry_has_linked_document_accessor(): void
    {
        $entryWith = Entry::factory()->create([
            'linked_document_path' => '2024\\Smith\\contract.pdf',
        ]);
        $entryWithout = Entry::factory()->create([
            'linked_document_path' => null,
        ]);

        $this->assertTrue($entryWith->has_linked_document);
        $this->assertFalse($entryWithout->has_linked_document);
    }

    public function test_entry_full_document_path_combines_base_and_relative(): void
    {
        $firm = Firm::factory()->create([
            'document_base_path' => 'S:\\Cases',
        ]);

        $entry = Entry::factory()->create([
            'firm_id' => $firm->id,
            'linked_document_path' => '2024\\Smith\\contract.pdf',
        ]);

        $expected = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'S:\\Cases\\2024\\Smith\\contract.pdf');
        $this->assertEquals($expected, $entry->fullDocumentPath());
    }

    public function test_entry_full_document_path_returns_null_when_no_linked_path(): void
    {
        $entry = Entry::factory()->create([
            'linked_document_path' => null,
        ]);

        $this->assertNull($entry->fullDocumentPath());
    }

    public function test_entry_full_document_path_returns_null_when_no_base_path(): void
    {
        $firm = Firm::factory()->create([
            'document_base_path' => null,
        ]);

        $entry = Entry::factory()->create([
            'firm_id' => $firm->id,
            'linked_document_path' => '2024\\Smith\\contract.pdf',
        ]);

        $this->assertNull($entry->fullDocumentPath());
    }

    public function test_entry_full_document_path_trims_trailing_separators(): void
    {
        $firm = Firm::factory()->create([
            'document_base_path' => 'S:\\Cases\\',
        ]);

        $entry = Entry::factory()->create([
            'firm_id' => $firm->id,
            'linked_document_path' => '2024\\Smith\\contract.pdf',
        ]);

        $expected = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, 'S:\\Cases\\2024\\Smith\\contract.pdf');
        $this->assertEquals($expected, $entry->fullDocumentPath());
    }
}
