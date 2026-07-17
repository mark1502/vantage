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

class CalendarGetEventsTest extends TestCase
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

    private function validParams(): array
    {
        return [
            'start' => now()->subDay()->toIso8601String(),
            'end' => now()->addDay()->toIso8601String(),
            'user1' => '1',
            'include_due' => 'false',
            'due_to' => 'false',
            'due_from' => 'false',
        ];
    }

    private function createCalendarEvent(Firm $firm, Contact $attendee): Entry
    {
        Folder::query()->find(6) ?? Folder::factory()->create(['id' => 6, 'name' => 'Events']);

        $entrytype = Entrytype::factory()->create([
            'firm_id' => $firm->id,
            'folder_id' => 6,
            'name' => 'Meeting',
        ]);

        $file = File::factory()->create(['firm_id' => $firm->id]);

        return Entry::factory()->create([
            'firm_id' => $firm->id,
            'file_id' => $file->id,
            'folder_id' => 6,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $attendee->id,
            'to_contact_id' => $attendee->id,
            'date1' => now(),
            'date2' => null,
            'all_day' => false,
            'on_calendar' => true,
            'note' => 'Test event',
        ]);
    }

    public function test_get_events_rejects_invalid_input_with_json_422(): void
    {
        [$user] = $this->createFirmUser();
        $base = $this->validParams();

        $missingStart = $base;
        unset($missingStart['start']);
        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($missingStart));
        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);

        $badStart = array_merge($base, ['start' => 'abc']);
        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($badStart));
        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);

        $badUser1 = array_merge($base, ['user1' => 'abc']);
        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($badUser1));
        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }

    public function test_get_events_shows_placeholder_for_missing_entrytype(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $event = $this->createCalendarEvent($firm, $contact);

        Entry::where('id', $event->id)->update(['entrytype_id' => 999999]);

        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($this->validParams()));

        $response->assertStatus(200);
        $found = collect($response->json())->firstWhere('id', $event->id);
        $this->assertNotNull($found);
        $this->assertStringContainsString('(missing event type)', $found['title']);
    }

    public function test_get_events_shows_placeholder_for_missing_to_contact(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $event = $this->createCalendarEvent($firm, $contact);

        Entry::where('id', $event->id)->update(['to_contact_id' => 999999]);

        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($this->validParams()));

        $response->assertStatus(200);
        $found = collect($response->json())->firstWhere('id', $event->id);
        $this->assertNotNull($found);
        $this->assertStringContainsString('??', $found['title']);
    }

    public function test_get_events_shows_placeholder_for_missing_file(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $event = $this->createCalendarEvent($firm, $contact);

        Entry::where('id', $event->id)->update(['file_id' => 999999]);

        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($this->validParams()));

        $response->assertStatus(200);
        $found = collect($response->json())->firstWhere('id', $event->id);
        $this->assertNotNull($found);
        $this->assertSame('(missing file)', $found['extendedProps']['file_name']);
    }

    public function test_get_events_include_due_toggle(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();

        Folder::factory()->create(['id' => 7, 'name' => 'Todo']);
        $entrytype = Entrytype::factory()->create([
            'firm_id' => $firm->id,
            'folder_id' => 7,
            'name' => 'Task',
        ]);
        $file = File::factory()->create(['firm_id' => $firm->id]);

        $dueEntry = Entry::factory()->create([
            'firm_id' => $firm->id,
            'file_id' => $file->id,
            'folder_id' => 7,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $contact->id,
            'to_contact_id' => $contact->id,
            'date1' => now(),
            'expecting_response' => true,
            'date_response_expected' => now(),
            'on_calendar' => false,
            'all_day' => false,
        ]);

        $params = $this->validParams();

        $withDue = array_merge($params, ['include_due' => 'true', 'due_to' => 'true', 'due_from' => 'true']);
        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($withDue));
        $response->assertStatus(200);
        $this->assertNotNull(collect($response->json())->firstWhere('id', $dueEntry->id));

        $withoutDue = array_merge($params, ['include_due' => 'false']);
        $response = $this->actingAs($user)->getJson('/get_events?'.http_build_query($withoutDue));
        $response->assertStatus(200);
        $this->assertNull(collect($response->json())->firstWhere('id', $dueEntry->id));
    }

    public function test_get_events_only_returns_own_firms_events(): void
    {
        [$userA, $firmA, $contactA] = $this->createFirmUser('AA');
        [, $firmB, $contactB] = $this->createFirmUser('BB');

        $eventA = $this->createCalendarEvent($firmA, $contactA);
        $eventB = $this->createCalendarEvent($firmB, $contactB);

        $response = $this->actingAs($userA)->getJson('/get_events?'.http_build_query($this->validParams()));

        $response->assertStatus(200);
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($eventA->id));
        $this->assertFalse($ids->contains($eventB->id));
    }
}
