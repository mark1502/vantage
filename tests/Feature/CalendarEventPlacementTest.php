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

class CalendarEventPlacementTest extends TestCase
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
            'date1' => '2026-01-10 10:00:00',
            'date2' => '2026-01-10 11:00:00',
            'all_day' => false,
            'on_calendar' => true,
            'note' => 'Test event',
        ]);
    }

    public function test_event_placement_moves_event_and_returns_success(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $event = $this->createCalendarEvent($firm, $contact);

        $response = $this->actingAs($user)->post('/event_placement', [
            'action' => 'move',
            'entry_id' => $event->id,
            'all_day' => false,
            'date1' => '2026-01-11 10:00:00',
            'date2' => '2026-01-11 11:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('entries', [
            'id' => $event->id,
            'date1' => '2026-01-11 10:00:00',
            'date2' => '2026-01-11 11:00:00',
        ]);
    }

    public function test_event_placement_missing_entry_returns_404(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->post('/event_placement', [
            'action' => 'move',
            'entry_id' => 999999,
            'all_day' => false,
            'date1' => '2026-01-11 10:00:00',
            'date2' => '2026-01-11 11:00:00',
        ]);

        $response->assertStatus(404);
    }

    public function test_event_placement_cross_firm_entry_returns_404_and_does_not_modify(): void
    {
        [$userA] = $this->createFirmUser('AA');
        [, $firmB, $contactB] = $this->createFirmUser('BB');

        $eventB = $this->createCalendarEvent($firmB, $contactB);

        $response = $this->actingAs($userA)->post('/event_placement', [
            'action' => 'move',
            'entry_id' => $eventB->id,
            'all_day' => false,
            'date1' => '2026-01-11 10:00:00',
            'date2' => '2026-01-11 11:00:00',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('entries', [
            'id' => $eventB->id,
            'date1' => '2026-01-10 10:00:00',
        ]);
    }

    public function test_event_placement_resize_updates_date2_only(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $event = $this->createCalendarEvent($firm, $contact);

        $response = $this->actingAs($user)->post('/event_placement', [
            'action' => 'resize',
            'entry_id' => $event->id,
            'all_day' => false,
            'date1' => '2026-01-10 10:00:00',
            'date2' => '2026-01-10 12:30:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('entries', [
            'id' => $event->id,
            'date1' => '2026-01-10 10:00:00',
            'date2' => '2026-01-10 12:30:00',
        ]);
    }

    public function test_event_placement_defaults_end_time_to_plus_one_hour_when_null(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $event = $this->createCalendarEvent($firm, $contact);

        $response = $this->actingAs($user)->post('/event_placement', [
            'action' => 'move',
            'entry_id' => $event->id,
            'all_day' => false,
            'date1' => '2026-01-12 09:00:00',
            'date2' => null,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('entries', [
            'id' => $event->id,
            'date1' => '2026-01-12 09:00:00',
            'date2' => '2026-01-12 10:00:00',
        ]);
    }
}
