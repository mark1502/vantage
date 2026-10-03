<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\File;
use App\Models\Filetype;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FiletypeIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createFirmUser(string $userType = 'Admin', string $initials = 'AA'): array
    {
        $firm = Firm::factory()->create();

        $user = User::factory()->create([
            'firm_id' => $firm->id,
            'welcomed' => true,
            'user_type' => $userType,
        ]);

        Contact::factory()->create([
            'firm_id' => $firm->id,
            'user_id' => $user->id,
            'is_firm_member' => true,
            'account_status' => 'A',
            'member_initials' => $initials,
        ]);

        return [$user, $firm];
    }

    private function createFiletype(Firm $firm, string $name, bool $default = false): Filetype
    {
        return Filetype::factory()->create([
            'firm_id' => $firm->id,
            'name' => $name,
            'set_as_default' => $default,
        ]);
    }

    public function test_non_admin_cannot_access_filetypes_index(): void
    {
        [$user, $firm] = $this->createFirmUser('User');
        $this->createFiletype($firm, 'Personal Injury');

        $this->actingAs($user)->get('/filetypes')->assertForbidden();
    }

    public function test_non_admin_cannot_delete_filetype(): void
    {
        [$user, $firm] = $this->createFirmUser('User');
        $keep = $this->createFiletype($firm, 'Keep');
        $target = $this->createFiletype($firm, 'Target');

        $this->actingAs($user)->delete(route('filetypes.destroy', $target))->assertForbidden();

        $this->assertDatabaseHas('filetypes', ['id' => $target->id]);
    }

    public function test_non_admin_cannot_set_default_filetype(): void
    {
        [$user, $firm] = $this->createFirmUser('User');
        $filetype = $this->createFiletype($firm, 'Personal Injury');

        $this->actingAs($user)->post('/setDefaultFileType', ['default_id' => $filetype->id])->assertForbidden();
    }

    public function test_index_search_filters_by_name_contains(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $this->createFiletype($firm, 'Personal Injury');
        $this->createFiletype($firm, 'Workers Comp');
        $this->createFiletype($firm, 'Injury Appeal');

        $this->actingAs($user)->get('/filetypes?search=injury')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Filetypes/Index')
                ->has('filetypes.data', 2)
                ->where('search', 'injury')
                ->where('filetypeCount', 3));
    }

    public function test_index_search_is_scoped_to_firm(): void
    {
        [$user, $firm] = $this->createFirmUser();
        [, $otherFirm] = $this->createFirmUser('Admin', 'BB');
        $this->createFiletype($firm, 'Personal Injury');
        $this->createFiletype($otherFirm, 'Other Injury');

        $this->actingAs($user)->get('/filetypes?search=injury')
            ->assertInertia(fn (Assert $page) => $page
                ->has('filetypes.data', 1)
                ->where('filetypes.data.0.name', 'Personal Injury'));
    }

    public function test_index_includes_files_count_and_filetype_count(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $used = $this->createFiletype($firm, 'Used');
        $this->createFiletype($firm, 'Unused');
        File::factory()->count(2)->create(['firm_id' => $firm->id, 'filetype_id' => $used->id]);

        $this->actingAs($user)->get('/filetypes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filetypeCount', 2)
                ->where('filetypes.data.0.name', 'Unused')
                ->where('filetypes.data.0.files_count', 0)
                ->where('filetypes.data.1.files_count', 2));
    }

    public function test_admin_can_delete_unused_filetype(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $this->createFiletype($firm, 'Keep');
        $target = $this->createFiletype($firm, 'Target');

        $this->actingAs($user)->delete(route('filetypes.destroy', $target))
            ->assertRedirect(route('filetypes.index'));

        $this->assertDatabaseMissing('filetypes', ['id' => $target->id]);
    }

    public function test_cannot_delete_filetype_used_by_open_file(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $this->createFiletype($firm, 'Keep');
        $target = $this->createFiletype($firm, 'Target');
        File::factory()->create(['firm_id' => $firm->id, 'filetype_id' => $target->id]);

        $this->actingAs($user)->delete(route('filetypes.destroy', $target))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('filetypes', ['id' => $target->id]);
    }

    public function test_cannot_delete_filetype_used_by_closed_file(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $this->createFiletype($firm, 'Keep');
        $target = $this->createFiletype($firm, 'Target');
        File::factory()->create([
            'firm_id' => $firm->id,
            'filetype_id' => $target->id,
            'date_closed' => now(),
        ]);

        $this->actingAs($user)->delete(route('filetypes.destroy', $target))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('filetypes', ['id' => $target->id]);
    }

    public function test_cannot_delete_default_filetype(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $this->createFiletype($firm, 'Keep');
        $target = $this->createFiletype($firm, 'Target', true);

        $this->actingAs($user)->delete(route('filetypes.destroy', $target))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('filetypes', ['id' => $target->id]);
    }

    public function test_cannot_delete_last_remaining_filetype(): void
    {
        [$user, $firm] = $this->createFirmUser();
        $target = $this->createFiletype($firm, 'Only');

        $this->actingAs($user)->delete(route('filetypes.destroy', $target))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('filetypes', ['id' => $target->id]);
    }

    public function test_cannot_delete_other_firms_filetype(): void
    {
        [$user, $firm] = $this->createFirmUser();
        [, $otherFirm] = $this->createFirmUser('Admin', 'BB');
        $this->createFiletype($firm, 'Mine');
        $this->createFiletype($otherFirm, 'Theirs 1');
        $target = $this->createFiletype($otherFirm, 'Theirs 2');

        $this->actingAs($user)->delete(route('filetypes.destroy', $target))->assertNotFound();

        $this->assertDatabaseHas('filetypes', ['id' => $target->id]);
    }

    public function test_delete_redirects_to_previous_page_when_current_page_becomes_empty(): void
    {
        [$user, $firm] = $this->createFirmUser();
        foreach (range(1, 6) as $i) {
            $this->createFiletype($firm, 'Type A'.$i);
        }
        $target = $this->createFiletype($firm, 'Type Z');

        $this->actingAs($user)
            ->delete(route('filetypes.destroy', $target), ['page' => 2, 'show' => 6])
            ->assertRedirect(route('filetypes.index', ['page' => 2, 'show' => 6]));

        $this->actingAs($user)->followingRedirects()->get('/filetypes?page=2&show=6')
            ->assertInertia(fn (Assert $page) => $page->where('filetypes.current_page', 1));
    }
}
