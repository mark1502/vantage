<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserContactTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function createFirmAdmin(string $initials = 'AA'): array
    {
        $firm = Firm::factory()->create();

        $user = User::factory()->create([
            'firm_id' => $firm->id,
            'welcomed' => true,
            'user_type' => 'Admin',
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
     * Register a throwing Contact::creating listener for the duration of the
     * callback, then restore the original listeners (which include the
     * BelongsToFirm trait's firm_id auto-stamp) so later tests are unaffected.
     */
    private function withContactCreatingFailure(callable $callback): void
    {
        $dispatcher = Contact::getEventDispatcher();
        $eventName = 'eloquent.creating: '.Contact::class;
        $originalListeners = $dispatcher->getListeners($eventName);

        Contact::creating(function (): void {
            throw new \RuntimeException('forced failure');
        });

        try {
            $callback();
        } finally {
            $dispatcher->forget($eventName);
            foreach ($originalListeners as $listener) {
                $dispatcher->listen($eventName, $listener);
            }
        }
    }

    private function newUserPayload(): array
    {
        return [
            'title' => 'Mr.',
            'first_name' => 'New',
            'last_name' => 'Attorney',
            'member_initials' => 'NA',
            'email' => 'new.attorney@example.com',
            'firm_role' => 'Attorney',
            'user_type' => 'Standard',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'display_name' => 'New Attorney',
            'display_last_first' => 'Attorney, New',
        ];
    }

    public function test_users_store_creates_user_and_contact_together(): void
    {
        [$admin] = $this->createFirmAdmin();

        $response = $this->actingAs($admin)->post(route('users.store'), $this->newUserPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new.attorney@example.com']);
        $newUser = User::where('email', 'new.attorney@example.com')->firstOrFail();
        $this->assertDatabaseHas('contacts', [
            'user_id' => $newUser->id,
            'member_initials' => 'NA',
        ]);
    }

    public function test_users_store_rolls_back_user_when_contact_creation_fails(): void
    {
        [$admin] = $this->createFirmAdmin();

        $this->withContactCreatingFailure(function () use ($admin): void {
            $this->withoutExceptionHandling();

            try {
                $this->actingAs($admin)->post(route('users.store'), $this->newUserPayload());
                $this->fail('Expected an exception to be thrown.');
            } catch (\RuntimeException $e) {
                $this->assertSame('forced failure', $e->getMessage());
            }
        });

        $this->assertDatabaseMissing('users', ['email' => 'new.attorney@example.com']);
    }

    private function addingAttorneyPayload(): array
    {
        return [
            'formtype' => 'addingAttorney',
            'title' => 'Ms.',
            'first_name' => 'Second',
            'last_name' => 'Attorney',
            'member_initials' => 'SA',
            'email' => 'second.attorney@example.com',
            'firm_role' => 'Attorney',
            'user_type' => 'Standard',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'display_name' => 'Second Attorney',
            'display_last_first' => 'Attorney, Second',
        ];
    }

    public function test_welcome_adding_attorney_creates_user_and_contact_together(): void
    {
        [$admin] = $this->createFirmAdmin();

        $response = $this->actingAs($admin)->post('/welcome_admin', $this->addingAttorneyPayload());

        $response->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'second.attorney@example.com']);
        $newUser = User::where('email', 'second.attorney@example.com')->firstOrFail();
        $this->assertDatabaseHas('contacts', [
            'user_id' => $newUser->id,
            'member_initials' => 'SA',
        ]);
    }

    public function test_welcome_adding_attorney_rolls_back_user_when_contact_creation_fails(): void
    {
        [$admin] = $this->createFirmAdmin();

        $this->withContactCreatingFailure(function () use ($admin): void {
            $this->withoutExceptionHandling();

            try {
                $this->actingAs($admin)->post('/welcome_admin', $this->addingAttorneyPayload());
                $this->fail('Expected an exception to be thrown.');
            } catch (\RuntimeException $e) {
                $this->assertSame('forced failure', $e->getMessage());
            }
        });

        $this->assertDatabaseMissing('users', ['email' => 'second.attorney@example.com']);
    }
}
