<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedPropsTest extends TestCase
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

    public function test_shared_auth_user_contains_only_the_expected_keys(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('auth.user', fn (Assert $authUser) => $authUser
                ->has('id')
                ->has('name')
                ->has('email')
                ->has('email_verified_at')
                ->has('user_type')
            )
            ->etc()
        );
    }

    public function test_files_index_shares_subscription_prop(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/files');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('subscription', fn (Assert $subscription) => $subscription
                ->has('file_count')
                ->has('file_limit')
                ->has('can_create_files')
            )
            ->etc()
        );
    }

    public function test_non_files_page_does_not_share_subscription_prop(): void
    {
        [$user] = $this->createFirmUser();

        $response = $this->actingAs($user)->get('/calendar');

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('subscription')
            ->etc()
        );
    }
}
