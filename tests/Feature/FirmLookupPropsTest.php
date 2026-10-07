<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Filetype;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FirmLookupPropsTest extends TestCase
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
            'firm_role' => 'Attorney',
        ]);

        return [$user, $firm, $contact];
    }

    public function test_entries_index_sends_active_members_and_attorneys_with_initials(): void
    {
        [$user, $firm] = $this->createFirmUser('AA');
        $file = File::factory()->create(['firm_id' => $firm->id, 'filetype_id' => Filetype::factory()->create(['firm_id' => $firm->id])->id]);

        $clerk = Contact::factory()->create([
            'firm_id' => $firm->id, 'is_firm_member' => true, 'account_status' => 'A',
            'member_initials' => 'CC', 'firm_role' => 'Clerical',
        ]);
        $inactive = Contact::factory()->create([
            'firm_id' => $firm->id, 'is_firm_member' => true, 'account_status' => 'I',
            'member_initials' => 'ZZ', 'firm_role' => 'Attorney',
        ]);

        $this->actingAs($user)->get("/files/{$file->id}/entries")
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($clerk, $inactive) {
                $page->where('firm_members', function ($members) use ($clerk, $inactive) {
                    $members = collect($members);

                    return $members->pluck('id')->contains($clerk->id)
                        && ! $members->pluck('id')->contains($inactive->id)
                        && $members->every(fn ($member) => array_key_exists('member_initials', (array) $member));
                })->where('attorneys', function ($attorneys) use ($clerk, $inactive) {
                    $attorneys = collect($attorneys);

                    return $attorneys->isNotEmpty()
                        && ! $attorneys->pluck('id')->contains($clerk->id)
                        && ! $attorneys->pluck('id')->contains($inactive->id)
                        && $attorneys->every(fn ($attorney) => $attorney['firm_role'] === 'Attorney');
                });
            });
    }

    public function test_entries_index_sends_empty_lookups_for_a_partial_refresh(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $file = File::factory()->create(['firm_id' => $firm->id, 'filetype_id' => Filetype::factory()->create(['firm_id' => $firm->id])->id]);

        $this->actingAs($user)->withHeaders(['X-Custom-Refresh' => 'Entries'])->get("/files/{$file->id}/entries")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('firm_members', [])
                ->where('attorneys', [])
                ->where('folders', [])
            );
    }

    public function test_views_index_sends_folders_with_narrowed_entrytype_columns(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $folder = Folder::factory()->create();
        Entrytype::factory()->create(['firm_id' => $firm->id, 'folder_id' => $folder->id, 'name' => 'Memo']);

        $this->actingAs($user)->get('/views?view=memos')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($folder) {
                $page->where('folders', function ($folders) use ($folder) {
                    $row = collect($folders)->firstWhere('id', $folder->id);
                    $entrytype = collect($row['entrytypes'])->first();

                    $keys = array_keys((array) $entrytype);
                    sort($keys);

                    return $keys === ['faux_deleted', 'folder_id', 'id', 'name'];
                });
            });
    }
}
