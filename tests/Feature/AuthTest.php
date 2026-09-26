<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->staff()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->staff()->create([
            'password' => bcrypt('password123'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_customer_cannot_login_on_web(): void
    {
        $user = User::factory()->customer()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Khách hàng chỉ hỗ trợ đăng nhập trên ứng dụng Mobile', session('errors')->get('email')[0]);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    /**
     * Tài khoản seed trong UserSeeder phải đăng nhập được với mật khẩu Hash::make('123456').
     */
    public function test_seeded_manager_and_staff_can_login_with_hashed_password(): void
    {
        $this->seed(\Database\Seeders\UserSeeder::class);

        foreach ([
            ['email' => 'admin@gmail.com', 'role' => 'admin'],
            ['email' => 'quanly@gmail.com', 'role' => 'manager'],
            ['email' => 'staff@gmail.com', 'role' => 'staff'],
            ['email' => 'nhanvien@gmail.com', 'role' => 'employee'],
        ] as $account) {
            $this->post('/login', [
                'email' => $account['email'],
                'password' => '123456',
            ]);

            $this->assertAuthenticated();
            $this->assertSame($account['role'], auth()->user()->role);

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_seeded_passwords_are_stored_as_hashes(): void
    {
        $this->seed(\Database\Seeders\UserSeeder::class);

        foreach (['admin@gmail.com', 'quanly@gmail.com', 'staff@gmail.com', 'nhanvien@gmail.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertNotSame('123456', $user->password, "$email chưa được mã hóa");
            $this->assertTrue(
                \Illuminate\Support\Facades\Hash::check('123456', $user->password),
                "$email không xác thực được với 123456"
            );
        }
    }
}
