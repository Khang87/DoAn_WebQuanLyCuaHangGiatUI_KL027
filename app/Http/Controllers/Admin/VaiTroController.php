<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Support\QuyenMapper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VaiTroController extends Controller
{
    public function __construct(
        private RoleService $roleService,
    ) {}

    /**
     * Màn hình ma trận phân quyền: cột dọc là quyền, hàng ngang là vai trò.
     */
    public function index(): View
    {
        Gate::authorize('roles.manage');

        return view('admin.roles.index', [
            'roles' => $this->roleService->getAllWithPermissions(),
            'permissions' => $this->roleService->getAllPermissions(),
            'ownerOnly' => QuyenMapper::ownerOnlyMaQuyens(),
        ]);
    }

    /**
     * Lưu ma trận quyền do Chủ cửa hàng tích chọn.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('roles.manage');

        $request->validate([
            'role_slugs' => ['required', 'array'],
            'role_slugs.*' => ['required', 'string'],
            'quyen_ids' => ['sometimes', 'array'],
            'quyen_ids.*' => ['array'],
            'quyen_ids.*.*' => ['integer', 'min:1'],
        ], [], [
            'role_slugs' => 'danh sách vai trò',
            'role_slugs.*' => 'vai trò',
            'quyen_ids' => 'ma trận phân quyền',
            'quyen_ids.*' => 'vai trò',
            'quyen_ids.*.*' => 'ID quyền',
        ]);

        /** @var list<string> $roleSlugs */
        $roleSlugs = array_values(array_unique($request->input('role_slugs', [])));
        /** @var array<string, array<int, int>> $submittedPermissions */
        $submittedPermissions = $request->input('quyen_ids', []);
        $matrix = array_fill_keys($roleSlugs, []);

        foreach ($submittedPermissions as $slug => $permissionIds) {
            if (array_key_exists($slug, $matrix)) {
                $matrix[$slug] = array_values(array_unique(array_map('intval', $permissionIds)));
            }
        }

        $result = $this->roleService->syncPermissions($matrix);

        $message = 'Đã cập nhật '.$result['granted'].' quyền hạn trong ma trận.';

        if ($result['rejected'] > 0) {
            $message .= ' Bỏ qua '.$result['rejected']
                .' lựa chọn đặc biệt chỉ dành cho Chủ cửa hàng.';
        }

        return redirect()->route('roles.index')->with('success', $message);
    }
}
