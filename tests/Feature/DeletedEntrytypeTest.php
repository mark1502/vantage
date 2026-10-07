<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Filetype;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeletedEntrytypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, firm: Firm, contact: Contact, file: File, folder: Folder, active: Entrytype, deleted: Entrytype}
     */
    private function createContext(?Folder $folder = null): array
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
            'member_initials' => 'AA',
        ]);

        $file = File::factory()->create([
            'firm_id' => $firm->id,
            'filetype_id' => Filetype::factory()->create(['firm_id' => $firm->id])->id,
        ]);

        $folder ??= Folder::factory()->create(['id' => random_int(1000, 999999)]);
        $active = Entrytype::factory()->create(['firm_id' => $firm->id, 'folder_id' => $folder->id]);
        $deleted = Entrytype::factory()->create(['firm_id' => $firm->id, 'folder_id' => $folder->id, 'faux_deleted' => true]);

        return compact('user', 'firm', 'contact', 'file', 'folder', 'active', 'deleted');
    }

    private function eventContext(): array
    {
        $eventFolder = Folder::query()->find(6) ?? Folder::factory()->create(['id' => 6, 'name' => 'Events']);

        return $this->createContext($eventFolder);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $ctx, Entrytype $entrytype, array $overrides = []): array
    {
        return array_merge([
            'file_id' => $ctx['file']->id,
            'folder_id' => $ctx['folder']->id,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $ctx['contact']->id,
            'note' => 'Test entry',
            'date1' => now()->format('Y-m-d H:i:s'),
            'is_a_response' => 'N',
            'was_a_response' => 'N',
            'current_page' => 1,
            'show' => 15,
            'filepart' => 'correspondence',
            'view' => 'memos',
            'view_for' => $ctx['contact']->member_initials,
            'viewpage' => 1,
            'read' => 'unread',
            'from_to' => 'to',
            'comeback' => false,
        ], $overrides);
    }

    private function createEntry(array $ctx, Entrytype $entrytype): Entry
    {
        return Entry::factory()->create([
            'firm_id' => $ctx['firm']->id,
            'file_id' => $ctx['file']->id,
            'folder_id' => $ctx['folder']->id,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $ctx['contact']->id,
            'date1' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarPayload(array $ctx, Entrytype $entrytype, array $overrides = []): array
    {
        return array_merge([
            'formtype' => 'calendar',
            'action' => 'add',
            'file_id' => $ctx['file']->id,
            'folder_id' => 6,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $ctx['contact']->id,
            'note' => 'Event',
            'all_day' => true,
            'date1' => now()->format('Y-m-d'),
            'date2' => null,
        ], $overrides);
    }

    public function test_entry_and_view_store_reject_a_deleted_entrytype(): void
    {
        $ctx = $this->createContext();

        $this->actingAs($ctx['user'])->post("/files/{$ctx['file']->id}/entries", $this->payload($ctx, $ctx['deleted']))
            ->assertSessionHasErrors('entrytype_id');

        $this->actingAs($ctx['user'])->post('/views', $this->payload($ctx, $ctx['deleted']))
            ->assertSessionHasErrors('entrytype_id');

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_entry_and_view_update_keep_the_current_deleted_entrytype(): void
    {
        $ctx = $this->createContext();
        $entry = $this->createEntry($ctx, $ctx['deleted']);

        $this->actingAs($ctx['user'])->put("/files/{$ctx['file']->id}/entries/{$entry->id}", $this->payload($ctx, $ctx['deleted']))
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($ctx['user'])->put("/views/{$entry->id}", $this->payload($ctx, $ctx['deleted']))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_entry_and_view_update_reject_switching_to_a_different_deleted_entrytype(): void
    {
        $ctx = $this->createContext();
        $otherDeleted = Entrytype::factory()->create([
            'firm_id' => $ctx['firm']->id,
            'folder_id' => $ctx['folder']->id,
            'faux_deleted' => true,
        ]);
        $entry = $this->createEntry($ctx, $ctx['deleted']);

        $this->actingAs($ctx['user'])->put("/files/{$ctx['file']->id}/entries/{$entry->id}", $this->payload($ctx, $otherDeleted))
            ->assertSessionHasErrors('entrytype_id');

        $this->actingAs($ctx['user'])->put("/views/{$entry->id}", $this->payload($ctx, $otherDeleted))
            ->assertSessionHasErrors('entrytype_id');
    }

    public function test_add_new_entrytype_restores_a_deleted_type_by_name(): void
    {
        $ctx = $this->createContext();
        $count = Entrytype::count();

        $this->actingAs($ctx['user'])->postJson('/add_new_entrytype', [
            'name' => strtoupper($ctx['deleted']->name),
            'folder_id' => $ctx['folder']->id,
        ])->assertOk()->assertJson(['id' => $ctx['deleted']->id, 'faux_deleted' => false]);

        $this->assertSame($count, Entrytype::count());
        $this->assertFalse($ctx['deleted']->fresh()->faux_deleted);
    }

    public function test_add_new_entrytype_creates_a_new_type(): void
    {
        $ctx = $this->createContext();
        $count = Entrytype::count();

        $this->actingAs($ctx['user'])->postJson('/add_new_entrytype', [
            'name' => 'Brand New Type',
            'folder_id' => $ctx['folder']->id,
        ])->assertOk()->assertJsonStructure(['id', 'folder_id', 'name', 'faux_deleted']);

        $this->assertSame($count + 1, Entrytype::count());
    }

    public function test_add_new_entrytype_returns_the_existing_active_type_without_inserting(): void
    {
        $ctx = $this->createContext();
        $count = Entrytype::count();

        $this->actingAs($ctx['user'])->postJson('/add_new_entrytype', [
            'name' => $ctx['active']->name,
            'folder_id' => $ctx['folder']->id,
        ])->assertOk()->assertJson(['id' => $ctx['active']->id]);

        $this->assertSame($count, Entrytype::count());
    }

    public function test_add_new_entrytype_rejects_an_empty_name(): void
    {
        $ctx = $this->createContext();

        $this->actingAs($ctx['user'])->postJson('/add_new_entrytype', [
            'name' => '',
            'folder_id' => $ctx['folder']->id,
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_entries_and_views_index_send_deleted_types_flagged(): void
    {
        $ctx = $this->createContext();
        $folderId = $ctx['folder']->id;
        $deletedId = $ctx['deleted']->id;

        $assertFlagged = function (Assert $page) use ($folderId, $deletedId) {
            $page->where('folders', function ($folders) use ($folderId, $deletedId) {
                $row = collect($folders)->firstWhere('id', $folderId);
                $type = collect($row['entrytypes'])->firstWhere('id', $deletedId);

                return $type !== null && $type['faux_deleted'] === true;
            });
        };

        $this->actingAs($ctx['user'])->get("/files/{$ctx['file']->id}/entries")->assertOk()->assertInertia($assertFlagged);
        $this->actingAs($ctx['user'])->get('/views?view=memos')->assertOk()->assertInertia($assertFlagged);
    }

    public function test_calendar_store_rejects_a_deleted_event_type_on_add(): void
    {
        $ctx = $this->eventContext();

        $response = $this->actingAs($ctx['user'])->postJson('/calendar', $this->calendarPayload($ctx, $ctx['deleted']));

        $response->assertStatus(422)->assertJsonValidationErrors('entrytype_id');
        $this->assertSame('That event type has been deleted. Please choose another.', $response->json('errors.entrytype_id.0'));
    }

    public function test_calendar_store_edit_keeps_current_deleted_type_but_rejects_another(): void
    {
        $ctx = $this->eventContext();
        $otherDeleted = Entrytype::factory()->create([
            'firm_id' => $ctx['firm']->id,
            'folder_id' => 6,
            'faux_deleted' => true,
        ]);
        $event = $this->createEntry($ctx, $ctx['deleted']);

        $this->actingAs($ctx['user'])->postJson('/calendar', $this->calendarPayload($ctx, $ctx['deleted'], [
            'action' => 'edit',
            'entry_id' => $event->id,
        ]))->assertOk();

        $this->actingAs($ctx['user'])->postJson('/calendar', $this->calendarPayload($ctx, $otherDeleted, [
            'action' => 'edit',
            'entry_id' => $event->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('entrytype_id');
    }

    public function test_add_new_entrytype_restores_a_deleted_event_type(): void
    {
        $ctx = $this->eventContext();
        $count = Entrytype::count();

        $this->actingAs($ctx['user'])->postJson('/add_new_entrytype', [
            'name' => strtoupper($ctx['deleted']->name),
            'folder_id' => 6,
        ])->assertOk()->assertJson(['id' => $ctx['deleted']->id, 'faux_deleted' => false]);

        $this->assertSame($count, Entrytype::count());
    }

    public function test_calendar_index_sends_deleted_event_types_flagged(): void
    {
        $ctx = $this->eventContext();
        $deletedId = $ctx['deleted']->id;

        $this->actingAs($ctx['user'])->get('/calendar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('event_types', function ($types) use ($deletedId) {
                $type = collect($types)->firstWhere('id', $deletedId);

                return $type !== null && $type['faux_deleted'] === true;
            }));
    }
}
