<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingContactRoleValidationTest extends TestCase
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

    private function entryPayload(Firm $firm, File $file, Contact $fromContact, Contact $roleContact, string $role): array
    {
        $folder = Folder::factory()->create();
        $entrytype = Entrytype::factory()->create([
            'firm_id' => $firm->id,
            'folder_id' => $folder->id,
        ]);

        return [
            'folder_id' => $folder->id,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $fromContact->id,
            'note' => 'Test entry',
            'date1' => now()->format('Y-m-d H:i:s'),
            'is_a_response' => 'N',
            'pending_contact_roles' => [
                ['contact_id' => $roleContact->id, 'role' => $role],
            ],
        ];
    }

    public function test_entries_store_rejects_bogus_pending_contact_role(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $file = File::factory()->create(['firm_id' => $firm->id]);

        $payload = $this->entryPayload($firm, $file, $contact, $contact, 'evil_role');

        $response = $this->actingAs($user)->post("/files/{$file->id}/entries", $payload);

        $response->assertSessionHasErrors('pending_contact_roles.0.role');
        $this->assertDatabaseMissing('contact_roles', ['role' => 'evil_role']);
    }

    public function test_entries_store_accepts_valid_pending_contact_role(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $file = File::factory()->create(['firm_id' => $firm->id]);

        $validRole = array_key_first(ContactRole::ROLE_LABELS);
        $payload = $this->entryPayload($firm, $file, $contact, $contact, $validRole);

        $response = $this->actingAs($user)->post("/files/{$file->id}/entries", $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contact_roles', [
            'file_id' => $file->id,
            'contact_id' => $contact->id,
            'role' => $validRole,
        ]);
    }

    public function test_views_store_rejects_bogus_pending_contact_role(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $file = File::factory()->create(['firm_id' => $firm->id]);
        $folder = Folder::factory()->create();
        $entrytype = Entrytype::factory()->create([
            'firm_id' => $firm->id,
            'folder_id' => $folder->id,
        ]);

        $payload = [
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $contact->id,
            'note' => 'Test view entry',
            'date1' => now()->format('Y-m-d H:i:s'),
            'is_a_response' => 'N',
            'was_a_response' => 'N',
            'current_page' => 1,
            'view' => 'memos',
            'view_for' => $contact->member_initials,
            'viewpage' => 1,
            'read' => 'unread',
            'from_to' => 'to',
            'pending_contact_roles' => [
                ['contact_id' => $contact->id, 'role' => 'evil_role'],
            ],
        ];

        $response = $this->actingAs($user)->post('/views', $payload);

        $response->assertSessionHasErrors('pending_contact_roles.0.role');
        $this->assertDatabaseMissing('contact_roles', ['role' => 'evil_role']);
    }
}
