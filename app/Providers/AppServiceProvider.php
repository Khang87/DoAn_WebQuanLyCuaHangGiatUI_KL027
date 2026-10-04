<?php

namespace App\Providers;

use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\Quyen;
use App\Models\User;
use App\Observers\HoaDonObserver;
use App\Observers\OrderObserver;
use App\Policies\UserPolicy;
use App\Support\PermissionRegistry;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;

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

        DB::prohibitDestructiveCommands();

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (
                $event->command === 'migrate'
                || str_starts_with($event->command, 'migrate:')
                || in_array($event->command, ['db:seed', 'make:migration', 'schema:dump'], true)
            ) {
                throw new LogicException(
                    "Artisan command [{$event->command}] is disabled to protect the live database schema and data."
                );
            }
        });

        Gate::policy(User::class, UserPolicy::class);

        HoaDon::observe(HoaDonObserver::class);
        DonHang::observe(OrderObserver::class);

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
            if (! $user->isActive()) {
                return false;
            }

            if (in_array($ability, self::RESERVED_ABILITIES, true)) {
                return null;
            }

            if ($user->isOwner()) {
                return true;
            }

            if (
                preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $ability) === 1
                && ! PermissionRegistry::isValidCode($ability)
                && Quyen::query()->where('MaQuyen', strtoupper(str_replace('.', '_', $ability)))->exists()
            ) {
                return $user->canPermission($ability);
            }

            return null;
        });

        foreach (PermissionRegistry::codes() as $code) {
            Gate::define($code, fn (User $user) => $user->canPermission($code));
        }
    }
}
