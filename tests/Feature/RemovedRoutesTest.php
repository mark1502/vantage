<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemovedRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function createFirmUser(): User
    {
        $firm = Firm::factory()->create();

        $user = User::factory()->create([
            'firm_id' => $firm->id,
            'welcomed' => true,
        ]);

        Contact::factory()->create([
            'firm_id' => $firm->id,
            'user_id' => $user->id,
            'is_firm_member' => true,
            'account_status' => 'A',
            'member_initials' => 'AA',
        ]);

        return $user;
    }

    public function test_contact_add_modal_routes_are_gone(): void
    {
        $user = $this->createFirmUser();

        $this->actingAs($user)->post('/contact_add_modal')->assertNotFound();
        $this->actingAs($user)->get('/contact_add_modal2')->assertNotFound();
    }

    public function test_add_new_entrytype_get_twin_is_gone(): void
    {
        $user = $this->createFirmUser();

        $this->actingAs($user)->get('/add_new_entrytype')->assertStatus(405);
    }

    public function test_add_new_event_type_routes_are_gone(): void
    {
        $user = $this->createFirmUser();

        $this->actingAs($user)->get('/add_new_event_type')->assertNotFound();
        $this->actingAs($user)->post('/add_new_event_type')->assertNotFound();
    }
}
