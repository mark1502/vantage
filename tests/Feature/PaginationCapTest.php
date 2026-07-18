<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaginationCapTest extends TestCase
{
    use RefreshDatabase;

    private function createFirmUser(string $userType = 'User'): array
    {
        $firm = Firm::factory()->create();

        $user = User::factory()->create([
            'firm_id' => $firm->id,
            'welcomed' => true,
            'user_type' => $userType,
        ]);

        $contact = Contact::factory()->create([
            'firm_id' => $firm->id,
            'user_id' => $user->id,
            'is_firm_member' => true,
            'account_status' => 'A',
            'member_initials' => 'AA',
        ]);

        return [$user, $firm, $contact];
    }

    public function test_entrytypes_index_caps_show_at_50(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/entrytypes?show=100000');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('entrytypes.per_page', 50));
    }

    public function test_entrytypes_index_handles_non_numeric_show(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/entrytypes?show=abc');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('entrytypes.per_page', 10));
    }

    public function test_folders_index_caps_show_at_50(): void
    {
        [$user] = $this->createFirmUser();
        Folder::factory()->count(2)->create();

        $response = $this->actingAs($user)->get('/folders?show=100000');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('folders.per_page', 50));
    }

    public function test_folders_index_handles_non_numeric_show(): void
    {
        [$user] = $this->createFirmUser();
        Folder::factory()->count(2)->create();

        $response = $this->actingAs($user)->get('/folders?show=abc');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('folders.per_page', 10));
    }

    public function test_users_index_caps_show_at_50(): void
    {
        [$user] = $this->createFirmUser('Admin');

        $response = $this->actingAs($user)->get('/users?show=100000');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('users.per_page', 50));
    }

    public function test_users_index_handles_non_numeric_show(): void
    {
        [$user] = $this->createFirmUser('Admin');

        $response = $this->actingAs($user)->get('/users?show=abc');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('users.per_page', 10));
    }

    public function test_views_index_caps_show_at_50(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/views?show=100000');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('entries.per_page', 50));
    }

    public function test_views_index_handles_non_numeric_show(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/views?show=abc');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('entries.per_page', 15));
    }
}
