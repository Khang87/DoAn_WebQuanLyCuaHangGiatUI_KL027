<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_list_services(): void
    {
        Service::create([
            'name' => 'Giặt Khô Cao Cấp',
            'price' => 80000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('services.index'));

        $response->assertStatus(200);
        $response->assertSee('Giặt Khô Cao Cấp');
    }

    public function test_can_create_service(): void
    {
        $response = $this->actingAs($this->admin)->post(route('services.store'), [
            'name' => 'Giặt Chăn Mền',
            'type' => 'Chăn mền',
            'price' => 120000,
            'unit' => 'món',
            'status' => 'active',
            'description' => 'Giặt hấp chăn mền dung tích lớn',
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', [
            'name' => 'Giặt Chăn Mền',
            'price' => 120000,
        ]);
    }

    public function test_can_update_service(): void
    {
        $service = Service::create([
            'name' => 'Ủi Đồ Thường',
            'price' => 20000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('services.update', $service), [
            'name' => 'Ủi Đồ Hấp',
            'price' => 30000,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Ủi Đồ Hấp',
            'price' => 30000,
        ]);
    }

    public function test_can_delete_service(): void
    {
        $service = Service::create([
            'name' => 'Dịch Vụ Cũ',
            'price' => 10000,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('services.destroy', $service));

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
        ]);
    }
}
