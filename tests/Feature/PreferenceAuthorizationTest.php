<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Firm;
use App\Models\Preference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferenceAuthorizationTest extends TestCase
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

    private function createPreference(User $user, string $name, string $setting): Preference
    {
        return Preference::create([
            'pref_default_id' => 1,
            'user_id' => $user->id,
            'firm_id' => $user->firm_id,
            'name' => $name,
            'prompt' => $name,
            'setting' => $setting,
        ]);
    }

    public function test_cross_firm_user_cannot_update_eventcolors(): void
    {
        [$owner] = $this->createFirmUser('AA');
        [$attacker] = $this->createFirmUser('BB');
        $this->createPreference($owner, 'event_bg', '#ffffff');
        $this->createPreference($owner, 'event_text', '#000000');

        $response = $this->actingAs($attacker)->post('/preferences/eventcolors', [
            'user_id' => $owner->id,
            'event_bg' => '#111111',
            'event_text' => '#222222',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('preferences', ['user_id' => $owner->id, 'name' => 'event_bg', 'setting' => '#ffffff']);
    }

    public function test_cross_firm_user_cannot_update_hover_placement(): void
    {
        [$owner] = $this->createFirmUser('AA');
        [$attacker] = $this->createFirmUser('BB');
        $this->createPreference($owner, 'event_hover_placement', 'upper_right');

        $response = $this->actingAs($attacker)->post('/preferences/hover_placement', [
            'user_id' => $owner->id,
            'event_hover_placement' => 'near_cursor',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('preferences', ['user_id' => $owner->id, 'name' => 'event_hover_placement', 'setting' => 'upper_right']);
    }

    public function test_cross_firm_user_cannot_update_theme(): void
    {
        [$owner] = $this->createFirmUser('AA');
        [$attacker] = $this->createFirmUser('BB');
        $this->createPreference($owner, 'theme', 'light');

        $response = $this->actingAs($attacker)->post('/preferences/theme', [
            'user_id' => $owner->id,
            'theme' => 'dark',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('preferences', ['user_id' => $owner->id, 'name' => 'theme', 'setting' => 'light']);
    }

    public function test_user_can_update_own_eventcolors(): void
    {
        [$user] = $this->createFirmUser();
        $this->createPreference($user, 'event_bg', '#ffffff');
        $this->createPreference($user, 'event_text', '#000000');

        $response = $this->actingAs($user)->post('/preferences/eventcolors', [
            'user_id' => $user->id,
            'event_bg' => '#111111',
            'event_text' => '#222222',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('preferences', ['user_id' => $user->id, 'name' => 'event_bg', 'setting' => '#111111']);
        $this->assertDatabaseHas('preferences', ['user_id' => $user->id, 'name' => 'event_text', 'setting' => '#222222']);
    }

    public function test_user_can_update_own_hover_placement(): void
    {
        [$user] = $this->createFirmUser();
        $this->createPreference($user, 'event_hover_placement', 'upper_right');

        $response = $this->actingAs($user)->post('/preferences/hover_placement', [
            'user_id' => $user->id,
            'event_hover_placement' => 'near_cursor',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('preferences', ['user_id' => $user->id, 'name' => 'event_hover_placement', 'setting' => 'near_cursor']);
    }

    public function test_user_can_update_own_theme(): void
    {
        [$user] = $this->createFirmUser();
        $this->createPreference($user, 'theme', 'light');

        $response = $this->actingAs($user)->post('/preferences/theme', [
            'user_id' => $user->id,
            'theme' => 'dark',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('preferences', ['user_id' => $user->id, 'name' => 'theme', 'setting' => 'dark']);
    }
}
