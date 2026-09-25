<?php

namespace Tests\Feature;

use App\Models\Garment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GarmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_list_garments(): void
    {
        Garment::create([
            'name' => 'Áo Dài Cao Cấp',
            'category' => 'Trang phục truyền thống',
            'price' => 70000,
            'condition_note' => 'Kiểm tra tà áo',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('garments.index'));

        $response->assertStatus(200);
        $response->assertSee('Áo Dài Cao Cấp');
    }

    public function test_can_create_garment(): void
    {
        $response = $this->actingAs($this->admin)->post(route('garments.store'), [
            'name' => 'Váy Cưới',
            'category' => 'Đồ cao cấp',
            'price' => 150000,
            'condition_note' => 'Đính hạt ren cẩn thận',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('garments.index'));
        $this->assertDatabaseHas('garments', [
            'name' => 'Váy Cưới',
            'price' => 150000,
        ]);
    }

    public function test_can_show_garment(): void
    {
        $garment = Garment::create([
            'name' => 'Áo Khoác Dạ',
            'category' => 'Áo khoác',
            'price' => 60000,
            'condition_note' => 'Sờn tay áo',
        ]);

        $response = $this->actingAs($this->admin)->get(route('garments.show', $garment->id));

        $response->assertStatus(200);
        $response->assertSee('Áo Khoác Dạ');
    }

    public function test_can_update_garment(): void
    {
        $garment = Garment::create([
            'name' => 'Quần Tây',
            'price' => 30000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('garments.update', $garment->id), [
            'name' => 'Quần Ân Cao Cấp',
            'price' => 35000,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('garments.index'));
        $this->assertDatabaseHas('garments', [
            'id' => $garment->id,
            'name' => 'Quần Ân Cao Cấp',
        ]);
    }

    public function test_can_delete_garment(): void
    {
        $garment = Garment::create([
            'name' => 'Đồ Cũ',
            'price' => 10000,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('garments.destroy', $garment->id));

        $response->assertRedirect(route('garments.index'));
        $this->assertSoftDeleted('garments', [
            'id' => $garment->id,
        ]);
    }
}
