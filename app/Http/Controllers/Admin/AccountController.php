<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        private UserService $userService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $accounts = $this->userService-> getAll([
            'search' => $request->input('search'),
            'role' => $request->input('role'),
            'status' => $request->input('status'),
        ]);

        return view('admin.accounts.index', compact('accounts'));
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('admin.accounts.create');
    }

    public function store(UserRequest $request)
    {
        $this->authorize('create', User::class);

        try {
            $user = $this->userService->create($request->validated());

            return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được tạo thành công với vai trò: ' . ($user->role === 'admin' ? 'Quản trị viên' : ($user->role === 'staff' ? 'Nhân viên' : 'Khách hàng')));
        } catch (\Exception $e) {
            return redirect()->route('accounts.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $account = $this->userService->find($id);

        if (!$account) {
            abort(404);
        }

        $this->authorize('view', [User::class, $account]);

        return view('admin.accounts.show', compact('account'));
    }

    public function edit(int $id)
    {
        $account = $this->userService->find($id);

        if (!$account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        return view('admin.accounts.edit', compact('account'));
    }

    public function update(UserRequest $request, int $id)
    {
        $account = $this->userService->find($id);

        if (!$account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        try {
            $this->userService->update($account, $request->validated());

            return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('accounts.edit', $account)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $account = $this->userService->find($id);

        if (!$account) {
            abort(404);
        }

        $this->authorize('delete', [User::class, $account]);

        try {
            $this->userService->delete($account);

            return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('accounts.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function toggleStatus(int $id)
    {
        $account = $this->userService->find($id);

        if (!$account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        try {
            if ($account->trashed()) {
                $account->restore();
                return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được kích hoạt.');
            } else {
                $account->delete();
                return redirect()->route('accounts.index')->with('success', 'Tài khoản đã bị khóa.');
            }
        } catch (\Exception $e) {
            return redirect()->route('accounts.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function resetPassword(int $id)
    {
        $account = $this->userService->find($id);

        if (!$account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        try {
            $account->update(['password' => Hash::make('Abc123!@#')]);

            return redirect()->route('accounts.show', $account)->with('success', 'Mật khẩu đã được đặt lại thành công. Mật khẩu mới: Abc123!@#');
        } catch (\Exception $e) {
            return redirect()->route('accounts.show', $account)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
