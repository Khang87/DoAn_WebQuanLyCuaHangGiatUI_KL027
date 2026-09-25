<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_list_accounts(): void
    {
        $response = $this->actingAs($this->admin)->get(route('accounts.index'));

        $response->assertStatus(200);
        $response->assertSee($this->admin->email);
    }

    public function test_admin_can_create_account_with_specified_role(): void
    {
        $response = $this->actingAs($this->admin)->post(route('accounts.store'), [
            'name' => 'Nhân Viên Mới',
            'email' => 'staff@example.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            'role' => 'staff',
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'staff@example.com',
            'role' => 'staff',
        ]);
    }

    public function test_new_account_defaults_to_customer_role(): void
    {
        $response = $this->actingAs($this->admin)->post(route('accounts.store'), [
            'name' => 'Khách Hàng Mới',
            'email' => 'customer@example.com',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('accounts.destroy', $this->admin));

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_admin_can_delete_other_user(): void
    {
        $otherUser = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($this->admin)->delete(route('accounts.destroy', $otherUser));

        $response->assertRedirect(route('accounts.index'));
        $this->assertSoftDeleted('users', ['id' => $otherUser->id]);
    }
}
