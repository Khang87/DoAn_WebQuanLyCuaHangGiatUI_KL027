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
            'sort' => $request->input('sort'),
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

            return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được tạo thành công với vai trò: ' . ($user->isManager() ? 'Quản lý' : ($user->role === 'staff' ? 'Nhân viên' : 'Khách hàng')));
        } catch (\Exception $e) {
            return redirect()->route('accounts.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
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
            return redirect()->route('accounts.edit', $account)->with('error', \App\Support\FriendlyError::message($e))->withInput();
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
            $result = $this->userService->delete($account);
        } catch (\Exception $e) {
            return redirect()->route('accounts.index')->with('error', \App\Support\FriendlyError::message($e));
        }

        if (!$result) {
            abort(422, 'Không thể xóa tài khoản đang đăng nhập.');
        }

        return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được xóa.');
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
            return redirect()->route('accounts.index')->with('error', \App\Support\FriendlyError::message($e));
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
            return redirect()->route('accounts.show', $account)->with('error', \App\Support\FriendlyError::message($e));
        }
    }

    public function profile()
    {
        $account = auth()->user();
        return view('admin.accounts.profile', compact('account'));
    }

    public function updateProfile(Request $request)
    {
        $account = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $account->id,
            'phone' => 'nullable|string|max:20',
        ]);

        try {
            $account->update($request->only(['name', 'email', 'phone']));

            if ($request->input('remove_avatar') === '1') {
                if ($account->getOriginal('avatar') && file_exists(public_path($account->getOriginal('avatar')))) {
                    unlink(public_path($account->getOriginal('avatar')));
                }
                $account->update(['avatar' => null]);
            }

            return redirect()->route('profile')->with('success', 'Hồ sơ đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('profile')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function updateAvatar(Request $request)
    {
        $account = auth()->user();

        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        try {
            if ($account->getOriginal('avatar') && file_exists(public_path($account->getOriginal('avatar')))) {
                unlink(public_path($account->getOriginal('avatar')));
            }

            $dir = public_path('uploads/avatars');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'avatar_' . $account->id . '_' . time() . '.' . $request->file('avatar')->getClientOriginalExtension();
            $request->file('avatar')->move($dir, $filename);

            $account->update(['avatar' => 'uploads/avatars/' . $filename]);
            $account->refresh();

            return response()->json([
                'success' => true,
                'avatar_url' => $account->avatar_url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => \App\Support\FriendlyError::message($e),
            ], 422);
        }
    }

    public function settings()
    {
        return view('admin.settings.index');
    }

    public function changePassword(Request $request)
    {
        $account = auth()->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_-]).+$/',
        ], [
            'new_password.regex' => 'Mật khẩu phải chứa ít nhất một chữ hoa, một chữ thường, một số và một ký tự đặc biệt.',
            'new_password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        if (!Hash::check($request->input('current_password'), $account->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.'])->withInput();
        }

        try {
            $account->update(['password' => Hash::make($request->input('new_password'))]);

            return redirect()->route('profile')->with('success', 'Mật khẩu đã được đổi thành công.');
        } catch (\Exception $e) {
            return back()->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }
}
