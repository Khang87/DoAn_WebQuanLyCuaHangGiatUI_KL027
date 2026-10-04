<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quyen;
use App\Models\TaiKhoan;
use App\Models\User;
use App\Models\VaiTro;
use App\Services\RoleService;
use App\Support\PermissionCache;
use App\Support\QuyenMapper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VaiTroController extends Controller
{
    public function __construct(
        private RoleService $roleService,
    ) {}

    /** Show the role cards with their active permissions and member counts. */
    public function index(): View
    {
        Gate::authorize('roles.manage');

        return view('admin.roles.index', $this->roleService->getRoleManagementData());
    }

    public function create(): View
    {
        Gate::authorize('roles.manage');

        return view('admin.roles.form');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $validated = $request->validate([
            'TenVaiTro' => ['required', 'string', 'max:100', 'unique:VaiTro,TenVaiTro'],
            'MoTa' => ['nullable', 'string', 'max:255'],
        ]);

        $role = $this->roleService->createRole($validated);

        return redirect()->route('roles.index')
            ->with('success', 'Đã thêm nhóm quyền '.$role->TenVaiTro.'. Tiếp theo, hãy chọn các quyền được cấp.');
    }

    public function edit(int $role): View
    {
        Gate::authorize('roles.manage');

        $vaiTro = VaiTro::query()
            ->with([
                'quyens',
                'taiKhoans.nhanVien',
                'taiKhoans.khachHang',
            ])
            ->findOrFail($role);
        $availableAccounts = TaiKhoan::query()
            ->with(['nhanVien', 'khachHang'])
            ->where('TrangThai', 'Hoạt động')
            ->whereDoesntHave('vaiTros', fn ($query) => $query->where('VaiTro.VaiTroID', $vaiTro->getKey()))
            ->orderBy('TenDangNhap')
            ->get();

        return view('admin.roles.form', [
            'role' => $vaiTro,
            'availableAccounts' => $availableAccounts,
            'permissionGroups' => $this->roleService->getActivePermissionGroups(),
            'ownerOnly' => QuyenMapper::ownerOnlyMaQuyens(),
        ]);
    }

    public function addMember(Request $request, int $role): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $vaiTro = VaiTro::query()->findOrFail($role);
        abort_if($vaiTro->isOwner(), 403, 'Không thể thay đổi thành viên của vai trò Chủ cửa hàng.');
        abort_if($vaiTro->TrangThai !== 'Hoạt động', 422, 'Không thể thêm thành viên vào nhóm đã ngừng hoạt động.');

        $validated = $request->validate([
            'TaiKhoanID' => ['required', 'integer', 'exists:TaiKhoan,TaiKhoanID,TrangThai,Hoạt động'],
        ]);
        $accountId = (int) $validated['TaiKhoanID'];

        abort_if(
            $request->user()->getKey() === $accountId,
            422,
            'Không thể tự thay đổi vai trò của tài khoản đang đăng nhập.',
        );

        DB::transaction(function () use ($vaiTro, $accountId): void {
            $lockedAccount = User::query()->lockForUpdate()->findOrFail($accountId);

            if ($lockedAccount->isOwner()) {
                throw ValidationException::withMessages([
                    'TaiKhoanID' => 'Không thể thay đổi vai trò của tài khoản Chủ cửa hàng.',
                ]);
            }

            $vaiTro->taiKhoans()->syncWithoutDetaching([$accountId]);
        });
        PermissionCache::forget($accountId);

        return redirect()->route('roles.edit', $vaiTro->getKey())
            ->with('success', 'Đã thêm tài khoản vào nhóm quyền.');
    }

    public function removeMember(Request $request, int $role, int $account): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $vaiTro = VaiTro::query()->findOrFail($role);
        abort_if($vaiTro->isOwner(), 403, 'Không thể thay đổi thành viên của vai trò Chủ cửa hàng.');
        abort_if(
            $request->user()->getKey() === $account,
            422,
            'Không thể tự thay đổi vai trò của tài khoản đang đăng nhập.',
        );

        DB::transaction(function () use ($vaiTro, $account): void {
            $lockedAccount = User::query()->lockForUpdate()->findOrFail($account);

            if ($lockedAccount->isOwner()) {
                throw ValidationException::withMessages([
                    'account' => 'Không thể thay đổi vai trò của tài khoản Chủ cửa hàng.',
                ]);
            }

            abort_unless($vaiTro->taiKhoans()->whereKey($account)->exists(), 404);
            $vaiTro->taiKhoans()->detach($account);
        });
        PermissionCache::forget($account);

        return redirect()->route('roles.edit', $vaiTro->getKey())
            ->with('success', 'Đã gỡ tài khoản khỏi nhóm quyền.');
    }

    public function updateRole(Request $request, int $role): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $vaiTro = VaiTro::query()->findOrFail($role);
        $validated = $request->validate([
            'TenVaiTro' => ['required', 'string', 'max:100', 'unique:VaiTro,TenVaiTro,'.$vaiTro->getKey().',VaiTroID'],
            'MoTa' => ['nullable', 'string', 'max:255'],
            'TrangThai' => ['required', 'in:Hoạt động,Ngừng hoạt động'],
        ]);

        $this->roleService->updateRole($vaiTro, $validated);

        return redirect()->route('roles.index')->with('success', 'Thông tin nhóm quyền đã được cập nhật.');
    }

    public function destroy(int $role): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $result = $this->roleService->deleteRole(VaiTro::query()->findOrFail($role));
        $message = $result === 'deleted'
            ? 'Nhóm quyền đã được xóa.'
            : 'Nhóm quyền đang được tài khoản sử dụng nên đã được vô hiệu hóa thay vì xóa.';

        return redirect()->route('roles.index')->with('success', $message);
    }

    public function syncPermissionRoles(Request $request, int $permission): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $quyen = Quyen::query()->findOrFail($permission);
        abort_unless($this->roleService->isManagedPermission($quyen), 404);
        abort_if($quyen->TrangThai !== 'Hoạt động', 422, 'Không thể thay đổi nhóm cho quyền đã ngừng hoạt động.');

        $validated = $request->validate([
            'vai_tro_ids' => ['sometimes', 'array'],
            'vai_tro_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:VaiTro,VaiTroID,TrangThai,Hoạt động',
            ],
        ]);

        $this->roleService->syncPermissionRoles($quyen, array_map(
            'intval',
            $validated['vai_tro_ids'] ?? [],
        ));

        return redirect()->route('roles.index')
            ->with('success', 'Nhóm quyền được cấp cho công việc đã được cập nhật.');
    }

    public function syncRolePermissions(Request $request, int $role): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $vaiTro = VaiTro::query()->findOrFail($role);
        abort_if($vaiTro->isOwner(), 403, 'Không thể thay đổi quyền của vai trò Chủ cửa hàng.');

        $validated = $request->validate([
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:Quyen,QuyenID,TrangThai,Hoạt động',
            ],
        ]);

        $this->roleService->syncRolePermissions(
            $vaiTro,
            array_map('intval', $validated['permission_ids'] ?? []),
        );

        return redirect()->route('roles.edit', $vaiTro->getKey())
            ->with('success', 'Quyền của nhóm đã được cập nhật.');
    }
}
