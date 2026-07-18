<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEventStoreTest extends TestCase
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

    public function test_storing_event_with_other_firms_entrytype_fails_validation(): void
    {
        [$userA, $firmA, $contactA] = $this->createFirmUser('AA');
        [, $firmB] = $this->createFirmUser('BB');

        Folder::query()->find(6) ?? Folder::factory()->create(['id' => 6, 'name' => 'Events']);

        $fileA = File::factory()->create(['firm_id' => $firmA->id]);
        $entrytypeB = Entrytype::factory()->create([
            'firm_id' => $firmB->id,
            'folder_id' => 6,
            'name' => 'Meeting',
        ]);

        $payload = [
            'formtype' => 'calendar',
            'action' => 'add',
            'file_id' => $fileA->id,
            'folder_id' => 6,
            'entrytype_id' => $entrytypeB->id,
            'from_contact_id' => $contactA->id,
            'note' => 'Cross-firm entrytype',
            'all_day' => true,
            'date1' => now()->format('Y-m-d'),
            'date2' => null,
        ];

        $response = $this->actingAs($userA)->postJson('/calendar', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('entrytype_id');
    }

    public function test_storing_event_with_own_firms_entrytype_succeeds(): void
    {
        [$userA, $firmA, $contactA] = $this->createFirmUser('AA');

        Folder::query()->find(6) ?? Folder::factory()->create(['id' => 6, 'name' => 'Events']);

        $fileA = File::factory()->create(['firm_id' => $firmA->id]);
        $entrytypeA = Entrytype::factory()->create([
            'firm_id' => $firmA->id,
            'folder_id' => 6,
            'name' => 'Meeting',
        ]);

        $payload = [
            'formtype' => 'calendar',
            'action' => 'add',
            'file_id' => $fileA->id,
            'folder_id' => 6,
            'entrytype_id' => $entrytypeA->id,
            'from_contact_id' => $contactA->id,
            'note' => 'Same-firm entrytype',
            'all_day' => true,
            'date1' => now()->format('Y-m-d'),
            'date2' => null,
        ];

        $response = $this->actingAs($userA)->postJson('/calendar', $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('entries', [
            'firm_id' => $firmA->id,
            'entrytype_id' => $entrytypeA->id,
            'note' => 'Same-firm entrytype',
        ]);

        $entry = Entry::where('note', 'Same-firm entrytype')->firstOrFail();
        $this->assertInstanceOf(Firm::class, $entry->firm);
        $this->assertSame($firmA->id, $entry->firm->id);
        $this->assertNotNull(Entry::with('firm')->find($entry->id)->firm);
    }
}
