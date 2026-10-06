<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarUrlTest extends TestCase
{
    private string $originalPublicPath;

    private string $testPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPublicPath = app()->publicPath();
        $this->testPublicPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sky-laundry-avatar-test-'.uniqid();
        app()->usePublicPath($this->testPublicPath);
    }

    protected function tearDown(): void
    {
        $avatarFiles = glob($this->testPublicPath.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'avatars'.DIRECTORY_SEPARATOR.'*') ?: [];

        foreach ($avatarFiles as $avatarFile) {
            if (is_file($avatarFile)) {
                unlink($avatarFile);
            }
        }

        if (is_dir($this->testPublicPath.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'avatars')) {
            rmdir($this->testPublicPath.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'avatars');
            rmdir($this->testPublicPath.DIRECTORY_SEPARATOR.'uploads');
        }

        app()->usePublicPath($this->originalPublicPath);

        parent::tearDown();
    }

    public function test_avatar_url_uses_the_same_stored_profile_image(): void
    {
        $avatarDirectory = $this->testPublicPath.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'avatars';
        mkdir($avatarDirectory, 0755, true);
        file_put_contents($avatarDirectory.DIRECTORY_SEPARATOR.'avatar_17.png', 'test-image');

        $user = new User;
        $user->setAttribute('TaiKhoanID', 17);

        $this->assertStringContainsString('/uploads/avatars/avatar_17.png', $user->avatar_url);
    }

    public function test_avatar_url_uses_deterministic_fallback_when_no_profile_image_exists(): void
    {
        $user = new User;
        $user->setAttribute('TaiKhoanID', 17);

        $this->assertStringEndsWith('/assets/images/user_2.jpg', $user->avatar_url);
    }

    public function test_avatar_url_uses_persisted_avatar_url_when_present(): void
    {
        $user = new User;
        $user->setAttribute('TaiKhoanID', 17);
        $user->setAttribute('AvatarURL', 'https://project.supabase.co/storage/v1/object/public/AVATARS/avatars/17');

        $this->assertSame(
            'https://project.supabase.co/storage/v1/object/public/AVATARS/avatars/17',
            $user->avatar_url,
        );
    }

    public function test_avatar_url_uses_predictable_supabase_object_path_when_storage_is_configured(): void
    {
        config([
            'filesystems.disks.supabase.key' => 'access-key',
            'filesystems.disks.supabase.secret' => 'secret-key',
            'filesystems.disks.supabase.bucket' => 'AVATARS',
            'filesystems.disks.supabase.endpoint' => 'https://project.supabase.co/storage/v1/s3',
            'filesystems.disks.supabase.url' => 'https://project.supabase.co/storage/v1/object/public/AVATARS',
        ]);
        Storage::shouldReceive('disk')
            ->once()
            ->with('supabase')
            ->andReturn(new class
            {
                public function url(string $path): string
                {
                    return 'https://project.supabase.co/storage/v1/object/public/AVATARS/'.$path;
                }
            });

        $user = new User;
        $user->setAttribute('TaiKhoanID', 17);

        $this->assertSame(
            'https://project.supabase.co/storage/v1/object/public/AVATARS/avatars/17',
            $user->avatar_url,
        );
    }
}
