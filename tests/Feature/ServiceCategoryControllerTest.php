<?php

namespace Tests\Feature;

use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_list_service_categories(): void
    {
        ServiceCategory::create([
            'name' => 'Giặt Lụa',
            'slug' => 'giat-lua',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('service-categories.index'));

        $response->assertStatus(200);
        $response->assertSee('Giặt Lụa');
    }

    public function test_index_renders_with_empty_result_set(): void
    {
        $response = $this->actingAs($this->admin)->get(route('service-categories.index'));

        $response->assertStatus(200);
        $response->assertSee('Chưa có dữ liệu nào');
    }

    public function test_index_renders_with_sort_query_parameters(): void
    {
        ServiceCategory::create([
            'name' => 'Hấp Sấy',
            'slug' => 'hap-say',
            'status' => 'active',
        ]);
        ServiceCategory::create([
            'name' => 'Ảnh Chụp',
            'slug' => 'anh-chup',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('service-categories.index', [
            'sort_by' => 'name',
            'sort_order' => 'asc',
        ]));

        $response->assertStatus(200);
    }

    public function test_index_renders_with_search_and_status_filters(): void
    {
        ServiceCategory::create([
            'name' => 'Vệ Sinh Sofa',
            'slug' => 've-sinh-sofa',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->admin)->get(route('service-categories.index', [
            'search' => 'Sofa',
            'status' => 'inactive',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Vệ Sinh Sofa');
    }

    public function test_can_create_service_category(): void
    {
        $response = $this->actingAs($this->admin)->post(route('service-categories.store'), [
            'name' => 'Giặt Bông',
            'slug' => 'giat-bong',
            'description' => 'Dịch vụ giặt bông',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('service-categories.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('service_categories', [
            'name' => 'Giặt Bông',
            'slug' => 'giat-bong',
        ]);
    }

    public function test_can_update_service_category(): void
    {
        $category = ServiceCategory::create([
            'name' => 'Giặt Khăn',
            'slug' => 'giat-khan',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('service-categories.update', $category->id), [
            'name' => 'Giặt Khăn Cao Cấp',
            'slug' => 'giat-khan-cao-cap',
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('service-categories.index'));
        $this->assertDatabaseHas('service_categories', [
            'id' => $category->id,
            'name' => 'Giặt Khăn Cao Cấp',
            'status' => 'inactive',
        ]);
    }

    public function test_can_delete_service_category(): void
    {
        $category = ServiceCategory::create([
            'name' => 'Dịch Vụ Cũ',
            'slug' => 'dich-vu-cu',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('service-categories.destroy', $category->id));

        $response->assertRedirect(route('service-categories.index'));
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('service_categories', [
            'id' => $category->id,
        ]);
    }
}
