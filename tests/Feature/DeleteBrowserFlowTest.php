<?php

namespace Tests\Feature;

use App\Models\Garment;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteBrowserFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Mô phỏng đúng những gì trình duyệt gửi: POST + _method=DELETE + _token.
     * Đây là đường đi mà $this->delete() KHÔNG kiểm tra.
     */
    public function test_delete_via_browser_style_post_spoofed_method(): void
    {
        $category = ServiceCategory::create([
            'name' => 'Browser Flow',
            'slug' => 'browser-flow',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('service-categories.destroy', $category->id),
            [
                '_method' => 'DELETE',
                '_token' => csrf_token(),
            ]
        );

        $response->assertStatus(302);
        $this->assertSoftDeleted('service_categories', ['id' => $category->id]);
    }

    /** Trang render có chứa đủ _token và _method cho form xóa không? */
    public function test_index_html_contains_csrf_and_method_override(): void
    {
        ServiceCategory::create([
            'name' => 'Check Tokens',
            'slug' => 'check-tokens',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('service-categories.index'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('_token', $content, 'Thiếu CSRF token');
        $this->assertStringContainsString('_method', $content, 'Thiếu method override DELETE');
        $this->assertStringContainsString('name="_method" value="DELETE"', $content);
    }

    /**
     * Sau khi xóa, bản ghi KHÔNG được xuất hiện lại trong danh sách.
     * Đây là kiểm chứng lỗi "bấm Xóa xong dữ liệu vẫn còn".
     */
    public function test_deleted_record_disappears_from_index(): void
    {
        $category = ServiceCategory::create([
            'name' => 'Sẽ Bị Xóa',
            'slug' => 'se-bi-xoa',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)->post(
            route('service-categories.destroy', $category->id),
            ['_method' => 'DELETE', '_token' => csrf_token()]
        )->assertStatus(302);

        $this->assertSoftDeleted('service_categories', ['id' => $category->id]);

        $response = $this->actingAs($this->admin)->get(route('service-categories.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Sẽ Bị Xóa');
    }

    /**
     * Bảo hàm toàn hệ thống: sau khi xóa, bản ghi phải biến mất khỏi danh sách
     * ở MỌI module dùng SoftDeletes (trước đây getAll() gọi withTrashed()
     * nên bản ghi vừa xóa lập tức hiện lại trong danh sách).
     */
    public function test_deleted_records_disappear_from_index_across_modules(): void
    {
        $guard = ServiceCategory::create([
            'name' => 'Danh mục còn lại',
            'slug' => 'danh-muc-con-lai',
            'status' => 'active',
        ]);
        $garment = Garment::create([
            'name' => 'Áo Xóa Đi',
            'category' => 'Áo',
            'price' => 50000,
        ]);

        $this->actingAs($this->admin)->post(
            route('service-categories.destroy', $category = ServiceCategory::create([
                'name' => 'Danh mục Xóa Đi',
                'slug' => 'danh-muc-xoa-di',
                'status' => 'active',
            ])->id),
            ['_method' => 'DELETE', '_token' => csrf_token()]
        )->assertStatus(302);

        $this->actingAs($this->admin)->delete(route('garments.destroy', $garment->id))
            ->assertStatus(302);

        $cats = $this->actingAs($this->admin)->get(route('service-categories.index'));
        $cats->assertStatus(200);
        $cats->assertDontSee('Danh mục Xóa Đi');
        $cats->assertSee('Danh mục còn lại');

        $gars = $this->actingAs($this->admin)->get(route('garments.index'));
        $gars->assertStatus(200);
        $gars->assertDontSee('Áo Xóa Đi');
    }

    /**
     * Bản ghi đã xóa vẫn phải mở/khôi phục được bằng URL trực tiếp
     * -> find() phải giữ withTrashed().
     */
    public function test_soft_deleted_record_is_still_reachable_by_id(): void
    {
        $category = ServiceCategory::create([
            'name' => 'Vẫn Mở Được',
            'slug' => 'van-mo-duoc',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)->post(
            route('service-categories.destroy', $category->id),
            ['_method' => 'DELETE', '_token' => csrf_token()]
        )->assertStatus(302);

        $this->assertSoftDeleted('service_categories', ['id' => $category->id]);

        $response = $this->actingAs($this->admin)->get(route('service-categories.show', $category->id));
        $response->assertStatus(200);
    }

    /** Form xóa có nằm trong <form> khác không (HTML cấm nested form)? */
    public function test_delete_form_is_not_nested(): void
    {
        ServiceCategory::create([
            'name' => 'Nesting Check',
            'slug' => 'nesting-check',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('service-categories.index'));
        $content = $response->getContent();

        preg_match_all('/<form\b[^>]*>/i', $content, $m);

        $this->assertNotEmpty($m[0]);

        // Mỗi </form> phải khớp 1-1 với <form>
        $open = count($m[0]);
        $close = substr_count(strtolower($content), '</form>');
        $this->assertSame($open, $close, 'Số <form> và </form> không khớp - có thể có nested form');
    }
}
