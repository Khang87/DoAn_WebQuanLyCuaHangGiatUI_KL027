<?php

namespace App\Providers;

use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\PermissionRegistry;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Các ability của Policy (viewAny/create/...) không đi qua bảng permissions
     * nên Gate::before phải bỏ qua, tránh nuốt mất kiểm tra của Policy.
     *
     * @var list<string>
     */
    private const RESERVED_ABILITIES = [
        'viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        require_once base_path('app/Support/helpers.php');

        Gate::policy(User::class, UserPolicy::class);

        Booking::observe(BookingObserver::class);

        Paginator::useBootstrapFive();

        $this->registerPermissionGates();
    }

    /**
     * Đăng ký mọi mã quyền trong PermissionRegistry thành Gate để dùng được cả
     * trong Blade (@can) lẫn trong Controller ($this->authorize).
     *
     * Danh sách mã lấy từ registry tĩnh nên không query DB lúc boot.
     * Gate::before cho phép Chủ cửa hàng bỏ qua mọi kiểm tra phân quyền.
     */
    private function registerPermissionGates(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (in_array($ability, self::RESERVED_ABILITIES, true)) {
                return null;
            }

            return $user->isOwner() ? true : null;
        });

        foreach (PermissionRegistry::codes() as $code) {
            Gate::define($code, fn (User $user) => $user->canPermission($code));
        }
    }
}
