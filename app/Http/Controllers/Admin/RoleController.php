<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    /**
     * Quyền đặc biệt chỉ Chủ cửa hàng được giữ (Role::OWNER_ONLY_PERMISSIONS cũ).
     *
     * @var list<string>
     */
    private const OWNER_ONLY_PERMISSIONS = [
        'orders.edit_completed',
        'orders.delete_completed',
        'invoices.edit_paid',
        'invoices.delete_paid',
        'orders.refund',
        'payments.refund',
        'payments.edit_paid',
        'payments.delete_paid',
        'roles.manage',
    ];

    public function __construct(
        private RoleService $roleService,
    ) {}

    /**
     * Màn hình ma trận phân quyền: cột dọc là quyền, hàng ngang là vai trò.
     */
    public function index()
    {
        Gate::authorize('roles.manage');

        return view('admin.roles.index', [
            'roles' => $this->roleService->getAllWithPermissions(),
            'matrix' => $this->roleService->matrix(),
            'groups' => array_keys(PermissionRegistry::groups()),
            'ownerOnly' => self::OWNER_ONLY_PERMISSIONS,
        ]);
    }

    /**
     * Lưu ma trận quyền do Chủ cửa hàng tích chọn.
     */
    public function update(Request $request)
    {
        Gate::authorize('roles.manage');

        $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string'],
        ], [], [
            'permissions' => 'ma trận phân quyền',
            'permissions.*' => 'vai trò',
            'permissions.*.*' => 'mã quyền',
        ]);

        /** @var array<string, array<int, string>> $matrix */
        $matrix = $request->input('permissions', []);

        $result = $this->roleService->syncPermissions($matrix);

        $message = 'Đã cập nhật ' . $result['granted'] . ' quyền hạn trong ma trận.';

        if ($result['rejected'] > 0) {
            $message .= ' Bỏ qua ' . $result['rejected']
                . ' lựa chọn đặc biệt chỉ dành cho Chủ cửa hàng.';
        }

        return redirect()->route('roles.index')->with('success', $message);
    }
}
