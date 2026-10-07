<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Filetype;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\Response;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryResponseFlagTest extends TestCase
{
    use RefreshDatabase;

    private const FULL_RESPONSE_ERROR = 'That entry already has a full response.';

    /**
     * @return array{user: User, firm: Firm, contact: Contact, file: File, folder: Folder, entrytype: Entrytype}
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
        $entrytype = Entrytype::factory()->create(['firm_id' => $firm->id, 'folder_id' => $folder->id]);

        return compact('user', 'firm', 'contact', 'file', 'folder', 'entrytype');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEntry(array $ctx, array $overrides = []): Entry
    {
        return Entry::factory()->create(array_merge([
            'firm_id' => $ctx['firm']->id,
            'file_id' => $ctx['file']->id,
            'folder_id' => $ctx['folder']->id,
            'entrytype_id' => $ctx['entrytype']->id,
            'from_contact_id' => $ctx['contact']->id,
            'date1' => now(),
        ], $overrides));
    }

    private function createTarget(array $ctx, bool $withDueDate = true): Entry
    {
        if (! $withDueDate) {
            return $this->createEntry($ctx, ['expecting_response' => false, 'date_response_expected' => null]);
        }

        return $this->createEntry($ctx, [
            'date_response_expected' => now()->addWeek()->format('Y-m-d H:i:s'),
            'expecting_response' => true,
        ]);
    }

    private function createResponse(Entry $from, Entry $to, string $type): void
    {
        $response = new Response;
        $response->entry_id = $from->id;
        $response->response_to = $to->id;
        $response->response_date = now();
        $response->response_type = $type;
        $response->save();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $ctx, array $overrides = []): array
    {
        return array_merge([
            'file_id' => $ctx['file']->id,
            'folder_id' => $ctx['folder']->id,
            'entrytype_id' => $ctx['entrytype']->id,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submit(string $via, array $ctx, array $payload, ?Entry $entry = null): \Illuminate\Testing\TestResponse
    {
        $client = $this->actingAs($ctx['user']);

        if ($via === 'entry') {
            return $entry
                ? $client->put("/files/{$entry->file_id}/entries/{$entry->id}", $payload)
                : $client->post("/files/{$ctx['file']->id}/entries", $payload);
        }

        return $entry
            ? $client->put("/views/{$entry->id}", $payload)
            : $client->post('/views', $payload);
    }

    private function respond(string $via, array $ctx, string $type, Entry $to): Entry
    {
        $this->submit($via, $ctx, $this->payload($ctx, ['is_a_response' => $type, 'is_response_to' => $to->id]))
            ->assertSessionDoesntHaveErrors();

        return Entry::query()->latest('id')->firstOrFail();
    }

    private function assertExpecting(Entry $target, bool $expected): void
    {
        $this->assertSame($expected, (bool) $target->fresh()->expecting_response);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function routes(): array
    {
        return ['entry routes' => ['entry'], 'view routes' => ['view']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routes')]
    public function test_full_response_clears_expecting_but_partial_does_not(string $via): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $due = (string) $target->date_response_expected;

        $this->respond($via, $ctx, 'F', $target);
        $this->assertExpecting($target, false);
        $this->assertSame($due, (string) $target->fresh()->date_response_expected);

        $other = $this->createTarget($ctx);
        $this->respond($via, $ctx, 'P', $other);
        $this->assertExpecting($other, true);
    }

    public function test_several_partial_responses_keep_target_expecting(): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);

        $this->respond('entry', $ctx, 'P', $target);
        $this->respond('entry', $ctx, 'P', $target);

        $this->assertExpecting($target, true);
        $this->assertDatabaseCount('responses', 2);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routes')]
    public function test_editing_full_to_partial_makes_target_expect_again(string $via): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $responder = $this->respond($via, $ctx, 'F', $target);

        $this->submit($via, $ctx, $this->payload($ctx, ['is_a_response' => 'P', 'is_response_to' => $target->id]), $responder)
            ->assertSessionDoesntHaveErrors();

        $this->assertExpecting($target, true);
    }

    public function test_editing_full_to_partial_leaves_target_without_due_date_not_expecting(): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx, false);
        $responder = $this->respond('entry', $ctx, 'F', $target);

        $this->submit('entry', $ctx, $this->payload($ctx, ['is_a_response' => 'P', 'is_response_to' => $target->id]), $responder);

        $this->assertExpecting($target, false);
    }

    public function test_editing_partial_to_full_clears_expecting(): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $responder = $this->respond('entry', $ctx, 'P', $target);
        $this->assertExpecting($target, true);

        $this->submit('entry', $ctx, $this->payload($ctx, ['is_a_response' => 'F', 'is_response_to' => $target->id]), $responder);

        $this->assertExpecting($target, false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routes')]
    public function test_repointing_a_full_response_updates_both_targets(string $via): void
    {
        $ctx = $this->createContext();
        $targetA = $this->createTarget($ctx);
        $targetC = $this->createTarget($ctx);
        $responder = $this->respond($via, $ctx, 'F', $targetA);
        $this->assertExpecting($targetA, false);

        $this->submit($via, $ctx, $this->payload($ctx, ['is_a_response' => 'F', 'is_response_to' => $targetC->id]), $responder);

        $this->assertExpecting($targetA, true);
        $this->assertExpecting($targetC, false);
        $this->assertDatabaseHas('responses', ['entry_id' => $responder->id, 'response_to' => $targetC->id]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routes')]
    public function test_changing_to_not_a_response_removes_the_row_and_restores_expecting(string $via): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $responder = $this->respond($via, $ctx, 'F', $target);

        $this->submit($via, $ctx, $this->payload($ctx, ['is_a_response' => 'N']), $responder);

        $this->assertDatabaseMissing('responses', ['entry_id' => $responder->id]);
        $this->assertExpecting($target, true);
    }

    public function test_deleting_the_responding_entry_removes_row_and_restores_expecting(): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $responder = $this->respond('entry', $ctx, 'F', $target);

        $this->actingAs($ctx['user'])->delete("/files/{$ctx['file']->id}/entries/{$responder->id}", [
            'entry_id' => $responder->id,
            'current_page' => 1,
            'show' => 15,
            'filepart' => 'correspondence',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseMissing('responses', ['entry_id' => $responder->id]);
        $this->assertDatabaseMissing('entries', ['id' => $responder->id]);
        $this->assertExpecting($target, true);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routes')]
    public function test_second_full_response_is_rejected(string $via): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $first = $this->createEntry($ctx);
        $this->createResponse($first, $target, 'F');
        $target->expecting_response = false;
        $target->save();

        $this->submit($via, $ctx, $this->payload($ctx, ['is_a_response' => 'F', 'is_response_to' => $target->id]))
            ->assertSessionHasErrors(['is_a_response' => self::FULL_RESPONSE_ERROR]);
        $this->assertDatabaseCount('responses', 1);

        $partial = $this->createEntry($ctx);
        $this->createResponse($partial, $target, 'P');

        $this->submit($via, $ctx, $this->payload($ctx, ['is_a_response' => 'F', 'is_response_to' => $target->id]), $partial)
            ->assertSessionHasErrors(['is_a_response' => self::FULL_RESPONSE_ERROR]);
        $this->assertDatabaseHas('responses', ['entry_id' => $partial->id, 'response_type' => 'P']);

        $this->submit($via, $ctx, $this->payload($ctx, ['is_a_response' => 'F', 'is_response_to' => $target->id]), $first)
            ->assertSessionDoesntHaveErrors();
    }

    public function test_response_without_a_target_leaves_existing_row_unchanged(): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $responder = $this->respond('entry', $ctx, 'F', $target);

        $this->submit('entry', $ctx, $this->payload($ctx, ['is_a_response' => 'P', 'is_response_to' => null]), $responder);

        $this->assertDatabaseHas('responses', [
            'entry_id' => $responder->id,
            'response_to' => $target->id,
            'response_type' => 'F',
        ]);
    }

    public function test_editing_the_target_view_recomputes_its_own_flag(): void
    {
        $ctx = $this->createContext();
        $target = $this->createTarget($ctx);
        $due = (string) $target->date_response_expected;

        $this->submit('view', $ctx, $this->payload($ctx, ['date_response_expected' => null]), $target);
        $this->assertExpecting($target, false);

        $this->submit('view', $ctx, $this->payload($ctx, ['date_response_expected' => $due]), $target);
        $this->assertExpecting($target, true);

        $responder = $this->createEntry($ctx);
        $this->createResponse($responder, $target, 'F');
        $this->submit('view', $ctx, $this->payload($ctx, ['date_response_expected' => $due]), $target);
        $this->assertExpecting($target, false);
    }

    public function test_editing_an_event_does_not_change_its_expecting_flag(): void
    {
        $eventFolder = Folder::query()->find(6) ?? Folder::factory()->create(['id' => 6, 'name' => 'Events']);
        $ctx = $this->createContext($eventFolder);
        $event = $this->createEntry($ctx, ['expecting_response' => false, 'date_response_expected' => now()->format('Y-m-d H:i:s')]);

        $this->submit('entry', $ctx, $this->payload($ctx), $event)->assertSessionDoesntHaveErrors();
        $this->assertExpecting($event, false);

        $this->submit('view', $ctx, $this->payload($ctx), $event)->assertSessionDoesntHaveErrors();
        $this->assertExpecting($event, false);
    }
}
