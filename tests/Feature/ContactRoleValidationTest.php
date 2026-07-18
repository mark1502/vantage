<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\File;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactRoleValidationTest extends TestCase
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

    public function test_store_with_missing_file_id_returns_validation_error(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();

        $response = $this->actingAs($user)->post('/contact-roles', [
            'contact_id' => $contact->id,
            'role' => array_key_first(ContactRole::ROLE_LABELS),
        ]);

        $response->assertSessionHasErrors('file_id');
    }

    public function test_store_with_nonexistent_contact_id_returns_validation_error_not_500(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $file = File::factory()->create(['firm_id' => $firm->id]);

        $response = $this->actingAs($user)->post('/contact-roles', [
            'file_id' => $file->id,
            'contact_id' => 999999,
            'role' => array_key_first(ContactRole::ROLE_LABELS),
        ]);

        $response->assertSessionHasErrors('contact_id');
    }

    public function test_store_with_cross_firm_contact_returns_specific_error_message(): void
    {
        [$user, $firm] = $this->createFirmUser('AA');
        [, , $otherFirmContact] = $this->createFirmUser('BB');
        $file = File::factory()->create(['firm_id' => $firm->id]);

        $response = $this->actingAs($user)->post('/contact-roles', [
            'file_id' => $file->id,
            'contact_id' => $otherFirmContact->id,
            'role' => array_key_first(ContactRole::ROLE_LABELS),
        ]);

        $response->assertSessionHasErrors(['contact_id' => 'You can only select contacts from your firm.']);
    }

    public function test_store_with_valid_file_and_contact_creates_contact_role(): void
    {
        [$user, $firm, $contact] = $this->createFirmUser();
        $file = File::factory()->create(['firm_id' => $firm->id]);
        $role = array_key_first(ContactRole::ROLE_LABELS);

        $response = $this->actingAs($user)->post('/contact-roles', [
            'file_id' => $file->id,
            'contact_id' => $contact->id,
            'role' => $role,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('contact_roles', [
            'file_id' => $file->id,
            'contact_id' => $contact->id,
            'role' => $role,
        ]);
    }
}
