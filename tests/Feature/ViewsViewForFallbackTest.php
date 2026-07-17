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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViewsViewForFallbackTest extends TestCase
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

    public function test_unmatched_view_for_falls_back_to_everyone(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/views?view=memos&view_for=ZZ');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->where('initials', '****'));
    }

    public function test_matched_view_for_still_filters(): void
    {
        [$userA, $firm, $contactA] = $this->createFirmUser('AA');
        $contactB = Contact::factory()->create([
            'firm_id' => $firm->id,
            'is_firm_member' => true,
            'account_status' => 'A',
            'member_initials' => 'BB',
        ]);

        Folder::query()->find(5) ?? Folder::factory()->create(['id' => 5, 'name' => 'Memos']);
        $entrytype = Entrytype::factory()->create([
            'firm_id' => $firm->id,
            'folder_id' => 5,
            'name' => 'Memo',
        ]);
        $file = File::factory()->create(['firm_id' => $firm->id]);

        $entryA = Entry::factory()->create([
            'firm_id' => $firm->id,
            'file_id' => $file->id,
            'folder_id' => 5,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $contactA->id,
            'to_contact_id' => null,
            'date1' => now(),
            'note' => 'From A',
        ]);

        Entry::factory()->create([
            'firm_id' => $firm->id,
            'file_id' => $file->id,
            'folder_id' => 5,
            'entrytype_id' => $entrytype->id,
            'from_contact_id' => $contactB->id,
            'to_contact_id' => null,
            'date1' => now(),
            'note' => 'From B',
        ]);

        $response = $this->actingAs($userA)->get('/views?view=memos&view_for=AA&from_to=from&read=both');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('initials', 'AA')
            ->has('entries.data', 1)
            ->where('entries.data.0.id', $entryA->id)
        );
    }
}
