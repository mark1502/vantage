<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactRequestValidationTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Mr.',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'display_name' => 'John Smith',
            'display_last_first' => 'Smith, John',
        ], $overrides);
    }

    public function test_modal_requires_names_for_a_person(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->postJson('/new_contact_modal', $this->contactPayload(['first_name' => '']));

        $response->assertStatus(422)->assertJsonValidationErrors('first_name');
    }

    public function test_modal_accepts_a_company_without_names(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->postJson('/new_contact_modal', $this->contactPayload([
            'title' => 'Co.',
            'company' => 'Acme Inc',
            'first_name' => '',
            'last_name' => '',
            'display_name' => 'Acme Inc',
            'display_last_first' => 'Acme Inc',
        ]));

        $response->assertOk()->assertJsonStructure(['added_contact_id', 'added_contact_name']);
    }

    public function test_modal_rejects_duplicate_display_name_in_same_firm(): void
    {
        [$user, $firm] = $this->createFirmUser();
        Contact::factory()->create(['firm_id' => $firm->id, 'display_name' => 'John Smith']);

        $response = $this->actingAs($user)->postJson('/new_contact_modal', $this->contactPayload());

        $response->assertStatus(422)->assertJsonValidationErrors('display_name');
        $this->assertStringContainsString('John Smith', $response->json('errors.display_name.0'));
    }

    public function test_modal_allows_same_display_name_in_another_firm(): void
    {
        [$user] = $this->createFirmUser('AA');
        [, $otherFirm] = $this->createFirmUser('BB');
        Contact::factory()->create(['firm_id' => $otherFirm->id, 'display_name' => 'John Smith']);

        $this->actingAs($user)->postJson('/new_contact_modal', $this->contactPayload())->assertOk();
    }

    public function test_contact_store_requires_display_name(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->post('/contacts', $this->contactPayload(['display_name' => '']));

        $response->assertSessionHasErrors('display_name');
    }

    public function test_contact_update_allows_keeping_own_display_name(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $contact = Contact::factory()->create(['firm_id' => $firm->id, 'display_name' => 'John Smith']);

        $response = $this->actingAs($user)->put("/contacts/{$contact->id}", $this->contactPayload(['note' => 'changed']));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'note' => 'changed']);
    }

    public function test_contact_update_rejects_another_contacts_display_name(): void
    {
        [$user, $firm] = $this->createFirmUser();
        Contact::factory()->create(['firm_id' => $firm->id, 'display_name' => 'Jane Doe']);
        $contact = Contact::factory()->create(['firm_id' => $firm->id, 'display_name' => 'John Smith']);

        $response = $this->actingAs($user)->put("/contacts/{$contact->id}", $this->contactPayload(['display_name' => 'Jane Doe']));

        $response->assertSessionHasErrors('display_name');
    }
}
