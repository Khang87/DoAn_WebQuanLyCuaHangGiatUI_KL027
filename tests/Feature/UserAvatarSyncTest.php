<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avatar người dùng phải đồng bộ giữa Header và Sidebar góc trái dưới,
 * và ô profile trên Header không được bị thu hẹp bởi kích thước cố định.
 */
class UserAvatarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_and_sidebar_use_the_same_avatar_source(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Nguyen Van A']);

        $html = $this->actingAs($admin)->get(route('orders.index'))->assertStatus(200)->getContent();

        preg_match('/<img[^>]*sidebar-profile-img[^>]*>/', $html, $sidebarTag);
        preg_match('/<img[^>]*avatar-cover[^>]*>/', $html, $headerTag);

        $this->assertNotEmpty($sidebarTag, 'Không tìm thấy avatar trong sidebar.');
        $this->assertNotEmpty($headerTag, 'Không tìm thấy avatar trong header.');

        preg_match('/src="([^"]+)"/', $sidebarTag[0], $sidebarSrc);
        preg_match('/src="([^"]+)"/', $headerTag[0], $headerSrc);

        $this->assertSame(
            $headerSrc[1],
            $sidebarSrc[1],
            'Avatar ở Header và Sidebar phải dùng cùng một nguồn ảnh.'
        );
    }

    public function test_avatar_falls_back_to_deterministic_image_when_no_avatar_column_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->get(route('orders.index'))->assertStatus(200)->getContent();

        $expected = 'user_' . (($admin->id % 8) + 1) . '.jpg';

        $this->assertStringContainsString($expected, $html);
    }

    public function test_header_profile_button_uses_expandable_class_and_no_inline_size(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->get(route('orders.index'))->assertStatus(200)->getContent();

        // Nút profile phải có class riêng để CSS nới rộng được
        $this->assertStringContainsString('user-profile-toggle', $html);

        // Không được hardcode width/height inline, vì sẽ đè lên CSS
        preg_match('/<img[^>]*avatar-cover[^>]*>/', $html, $tag);
        $this->assertNotEmpty($tag);
        $this->assertStringNotContainsString('style="width', $tag[0]);
    }

    public function test_only_user_menu_button_gets_the_expandable_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->get(route('orders.index'))->assertStatus(200)->getContent();

        // Nút chuông và lối tắt nhanh phải giữ nguyên kích thước vuông 40px
        $this->assertSame(
            1,
            substr_count($html, 'user-profile-toggle'),
            'Chỉ nút User Menu được gắn class nới rộng.'
        );

        $this->assertStringContainsString(
            '<button class="navbar-action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">' . "\n"
            . '                        <i class="bi bi-bell"></i>',
            $html,
            'Nút thông báo không được bị ảnh hưởng.'
        );
    }
}
