<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuTaiKhoanRequest;
use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Models\User;
use App\Models\VaiTro;
use App\Services\UserService;
use App\Support\FriendlyError;
use App\Support\PermissionCache;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TaiKhoanController extends Controller
{
    use AuthorizesRequests;

    private const OWNER_ROLE_ID = 1;

    public function __construct(
        private UserService $userService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['nhanVien', 'khachHang', 'vaiTros']);

        if ($search = trim((string) $request->input('search'))) {
            $pattern = '%'.mb_strtolower($search).'%';
            $query->where(function ($accountQuery) use ($pattern): void {
                $accountQuery->whereRaw('LOWER("TaiKhoan"."TenDangNhap") LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER("TaiKhoan"."Email") LIKE ?', [$pattern])
                    ->orWhereHas('nhanVien', fn ($staffQuery) => $staffQuery->whereRaw('LOWER("HoTen") LIKE ?', [$pattern]))
                    ->orWhereHas('khachHang', fn ($customerQuery) => $customerQuery->whereRaw('LOWER("HoTen") LIKE ?', [$pattern]));
            });
        }

        if ($roleId = $request->integer('role_id')) {
            $query->whereHas('vaiTros', fn ($roleQuery) => $roleQuery->where('VaiTro.VaiTroID', $roleId));
        }

        if ($request->input('status') === 'active') {
            $query->where('TrangThai', 'Hoạt động');
        } elseif ($request->input('status') === 'inactive') {
            $query->whereIn('TrangThai', ['Khóa', 'Ngừng hoạt động']);
        }

        match ($request->input('sort')) {
            'oldest', 'created_at_asc' => $query->orderBy('NgayTao'),
            'name_asc' => $query->orderBy('TenDangNhap'),
            'name_desc' => $query->orderByDesc('TenDangNhap'),
            'email_asc' => $query->orderBy('Email'),
            'email_desc' => $query->orderByDesc('Email'),
            default => $query->orderByDesc('NgayTao'),
        };

        $accounts = $query->paginate(10)->withQueryString();
        $roles = VaiTro::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('VaiTroID')
            ->get(['VaiTroID', 'TenVaiTro']);
        $canManageRoles = $this->hasOwnerRole($request->user());

        return view('admin.accounts.index', compact('accounts', 'roles', 'canManageRoles'));
    }

    /**
     * Cập nhật danh sách vai trò cho tài khoản, chỉ cho phép Chủ cửa hàng.
     */
    public function updateRole(Request $request, int $taiKhoanId): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->hasOwnerRole($actor), 403);

        $validated = $request->validate([
            'vai_tro_ids' => ['required', 'array', 'min:1'],
            'vai_tro_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:VaiTro,VaiTroID,TrangThai,Hoạt động',
            ],
        ]);

        $account = User::query()->findOrFail($taiKhoanId);

        abort_if(
            $actor->getKey() === $account->getKey(),
            422,
            'Không thể thay đổi vai trò của chính tài khoản đang đăng nhập.',
        );

        DB::transaction(function () use ($account, $validated): void {
            $account->vaiTros()->sync(array_map('intval', $validated['vai_tro_ids']));
        });

        PermissionCache::forget((int) $account->getKey());

        if (config('session.driver') === 'database') {
            $connection = config('session.connection');
            $sessionsTable = (string) config('session.table', 'sessions');

            if (Schema::connection($connection)->hasTable($sessionsTable)) {
                DB::connection($connection)
                    ->table($sessionsTable)
                    ->where('user_id', $account->getKey())
                    ->delete();
            }
        }

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Vai trò đã được cập nhật. Các phiên đăng nhập hiện có của tài khoản đã bị đăng xuất để áp dụng quyền mới.');
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $employees = NhanVien::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('HoTen')
            ->get(['NhanVienID', 'HoTen', 'SoDienThoai']);
        $customers = KhachHang::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('HoTen')
            ->get(['KhachHangID', 'HoTen', 'SoDienThoai']);

        return view('admin.accounts.create', compact('employees', 'customers'));
    }

    public function store(LuuTaiKhoanRequest $request)
    {
        $this->authorize('create', User::class);

        try {
            $user = $this->userService->create($request->validated());

            return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được tạo thành công với vai trò: '.($user->isManager() ? 'Quản lý' : ($user->role === 'staff' ? 'Nhân viên' : 'Khách hàng')));
        } catch (\Exception $e) {
            return redirect()->route('accounts.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $account = $this->userService->find($id);

        if (! $account) {
            abort(404);
        }

        $this->authorize('view', [User::class, $account]);

        return view('admin.accounts.show', compact('account'));
    }

    public function edit(int $id)
    {
        $account = $this->userService->find($id);

        if (! $account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        return view('admin.accounts.edit', compact('account'));
    }

    public function update(LuuTaiKhoanRequest $request, int $id)
    {
        $account = $this->userService->find($id);

        if (! $account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        // CHỈ CHO PHÉP ĐỔI MẬT KHẨU NẾU LÀ TÀI KHOẢN CỦA CHÍNH MÌNH
        if ($request->filled('password') && (int) auth()->id() !== (int) $account->getKey()) {
            return back()->withInput()->withErrors([
                'password' => 'Bạn không có quyền thay đổi mật khẩu của tài khoản khác!',
            ]);
        }

        try {
            $this->userService->update($account, $request->validated());

            return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('accounts.edit', $account)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $account = $this->userService->find($id);

        if (! $account) {
            abort(404);
        }

        $this->authorize('delete', [User::class, $account]);

        try {
            $result = $this->userService->delete($account);
        } catch (\Exception $e) {
            return redirect()->route('accounts.index')->with('error', FriendlyError::message($e));
        }

        if (! $result) {
            abort(422, 'Không thể xóa tài khoản đang đăng nhập.');
        }

        $message = $account->exists
            ? 'Tài khoản có dữ liệu liên quan nên đã được vô hiệu hóa.'
            : 'Tài khoản đã được xóa.';

        return redirect()->route('accounts.index')->with('success', $message);
    }

    public function toggleStatus(int $id)
    {
        $account = $this->userService->find($id);

        if (! $account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        try {
            if ($account->TrangThai !== 'Hoạt động') {
                $this->userService->restore($id);

                return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được kích hoạt.');
            } else {
                $this->userService->setStatus($account, 'Khóa');

                return redirect()->route('accounts.index')->with('success', 'Tài khoản đã bị khóa.');
            }
        } catch (\Exception $e) {
            return redirect()->route('accounts.index')->with('error', FriendlyError::message($e));
        }
    }

    public function resetPassword(int $id)
    {
        $account = $this->userService->find($id);

        if (! $account) {
            abort(404);
        }

        $this->authorize('update', [User::class, $account]);

        try {
            $this->userService->update($account, ['password' => 'Abc123!@#']);

            return redirect()->route('accounts.show', $account)->with('success', 'Mật khẩu đã được đặt lại thành công. Mật khẩu mới: Abc123!@#');
        } catch (\Exception $e) {
            return redirect()->route('accounts.show', $account)->with('error', FriendlyError::message($e));
        }
    }

    public function profile(): View
    {
        $account = auth()->user();

        return view('admin.accounts.profile', compact('account'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $account = auth()->user();
        $account->loadMissing(['nhanVien', 'khachHang']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:150',
                'unique:TaiKhoan,Email,'.$account->getKey().',TaiKhoanID',
            ],
            'phone' => [
                $account->nhanVien ? 'required' : 'nullable',
                'string',
                'max:15',
            ],
        ]);

        try {
            DB::transaction(function () use ($account, $validated): void {
                $accountData = [
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'username' => $account->TenDangNhap,
                ];

                if (! $account->nhanVien && ! $account->khachHang) {
                    $accountData['username'] = $validated['name'];
                }

                $this->userService->update($account, $accountData);

                $profileAttributes = [
                    'HoTen' => $validated['name'],
                    'Email' => $validated['email'],
                    'SoDienThoai' => $validated['phone'] ?? null,
                ];

                if ($account->nhanVien) {
                    $account->nhanVien->update($profileAttributes);
                } elseif ($account->khachHang) {
                    $account->khachHang->update($profileAttributes);
                }
            });

            if ($request->input('remove_avatar') === '1') {
                $this->deleteAvatarFiles($account);
            }

            return redirect()->route('profile')->with('success', 'Hồ sơ đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('profile')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function updateAvatar(Request $request)
    {
        $account = auth()->user();

        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        try {
            $dir = public_path('uploads/avatars');
            if (! is_dir($dir)) {
                if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                    throw new \RuntimeException('Không thể tạo thư mục lưu ảnh đại diện.');
                }
            }

            $filename = 'avatar_'.$account->getKey().'.'.$request->file('avatar')->extension();
            $avatarFile = $request->file('avatar')->move($dir, $filename);
            $this->deleteAvatarFiles($account, $avatarFile->getPathname());

            return response()->json([
                'success' => true,
                'avatar_url' => $account->avatar_url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => FriendlyError::message($e),
            ], 422);
        }
    }

    private function deleteAvatarFiles(User $account, ?string $exceptPath = null): void
    {
        $avatarFiles = glob(public_path('uploads/avatars/avatar_'.$account->getKey().'.*')) ?: [];
        $exceptPath = $exceptPath ? realpath($exceptPath) : null;

        foreach ($avatarFiles as $avatarFile) {
            if (! is_file($avatarFile) || ($exceptPath && realpath($avatarFile) === $exceptPath)) {
                continue;
            }

            if (! unlink($avatarFile)) {
                throw new \RuntimeException('Không thể xóa ảnh đại diện cũ.');
            }
        }
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

        if (! Hash::check($request->input('current_password'), $account->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.'])->withInput();
        }

        try {
            $this->userService->update($account, ['password' => $request->input('new_password')]);

            return redirect()->route('profile')->with('success', 'Mật khẩu đã được đổi thành công.');
        } catch (\Exception $e) {
            return back()->with('error', FriendlyError::message($e))->withInput();
        }
    }

    private function hasOwnerRole(mixed $user): bool
    {
        return $user instanceof User
            && $user->vaiTros()
                ->where('VaiTro.VaiTroID', self::OWNER_ROLE_ID)
                ->exists();
    }
}
