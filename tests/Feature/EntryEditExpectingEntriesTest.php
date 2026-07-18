<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\Response;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EntryEditExpectingEntriesTest extends TestCase
{
    use RefreshDatabase;

    private function createFirmUser(string $initials = 'AA'): array
    {
        $firm = Firm::factory()->create();

        $user = User::factory()->create([
            'firm_id' => $firm->id,
            'welcomed' => true,
        ]);

        $contact = Contact::factory()->create([
            'firm_id' => $firm->id,
            'user_id' => $user->id,
            'is_firm_member' => true,
            'account_status' => 'A',
            'member_initials' => $initials,
        ]);

        return [$user, $firm, $contact];
    }

    private function createEntry(Firm $firm, File $file, Folder $folder, Entrytype $entrytype, Contact $contact, array $overrides = []): Entry
    {
        return Entry::factory()->create(array_merge([
            'firm_id' => $firm->id,
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $contact->id,
            'date1' => now(),
        ], $overrides));
    }

    public function test_expecting_entries_excludes_other_files_when_entry_has_no_response(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();

        $fileA = File::factory()->create(['firm_id' => $firm->id]);
        $fileB = File::factory()->create(['firm_id' => $firm->id]);
        $folder = Folder::factory()->create();
        $entrytype = Entrytype::factory()->create(['firm_id' => $firm->id, 'folder_id' => $folder->id]);

        $entryToEdit = $this->createEntry($firm, $fileA, $folder, $entrytype, $contact);
        $e1 = $this->createEntry($firm, $fileA, $folder, $entrytype, $contact, ['expecting_response' => true]);
        $e2 = $this->createEntry($firm, $fileB, $folder, $entrytype, $contact, ['expecting_response' => true]);

        $response = $this->actingAs($user)->get("/files/{$fileA->id}/entries/{$entryToEdit->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Entries/EntryForm')
            ->has('expecting_entries', 1)
            ->where('expecting_entries.0.id', $e1->id)
        );
    }

    public function test_expecting_entries_includes_response_to_but_excludes_other_files_when_entry_is_a_response(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();

        $fileA = File::factory()->create(['firm_id' => $firm->id]);
        $fileB = File::factory()->create(['firm_id' => $firm->id]);
        $folder = Folder::factory()->create();
        $entrytype = Entrytype::factory()->create(['firm_id' => $firm->id, 'folder_id' => $folder->id]);

        $e1 = $this->createEntry($firm, $fileA, $folder, $entrytype, $contact, ['expecting_response' => true]);
        $e2 = $this->createEntry($firm, $fileB, $folder, $entrytype, $contact, ['expecting_response' => true]);
        $respondedTo = $this->createEntry($firm, $fileA, $folder, $entrytype, $contact);
        $entryR = $this->createEntry($firm, $fileA, $folder, $entrytype, $contact);

        $newResponse = new Response;
        $newResponse->entry_id = $entryR->id;
        $newResponse->response_to = $respondedTo->id;
        $newResponse->response_date = now();
        $newResponse->response_type = 'F';
        $newResponse->save();

        $response = $this->actingAs($user)->get("/files/{$fileA->id}/entries/{$entryR->id}/edit");

        $response->assertOk();
        $response->assertInertia(function (Assert $page) use ($e1, $e2, $respondedTo) {
            $page->component('Entries/EntryForm')
                ->has('expecting_entries', 2)
                ->where('expecting_entries', function ($entries) use ($e1, $e2, $respondedTo) {
                    $ids = collect($entries)->pluck('id');

                    return $ids->contains($e1->id)
                        && $ids->contains($respondedTo->id)
                        && ! $ids->contains($e2->id);
                });
        });
    }
}
