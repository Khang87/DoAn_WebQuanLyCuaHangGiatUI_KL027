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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TaiKhoanController extends Controller
{
    use AuthorizesRequests;

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
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('TaiKhoan')
                    ->whereColumn('TaiKhoan.NhanVienID', 'NhanVien.NhanVienID');
            })
            ->orderBy('HoTen')
            ->get(['NhanVienID', 'HoTen', 'SoDienThoai']);
        $customers = KhachHang::query()
            ->where('TrangThai', 'Hoạt động')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('TaiKhoan')
                    ->whereColumn('TaiKhoan.KhachHangID', 'KhachHang.KhachHangID');
            })
            ->orderBy('HoTen')
            ->get(['KhachHangID', 'HoTen', 'SoDienThoai']);

        return view('admin.accounts.create', compact('employees', 'customers'));
    }

    public function store(LuuTaiKhoanRequest $request)
    {
        $this->authorize('create', User::class);

        try {
            $user = $this->userService->create($request->validated());

            $roleName = $user->vaiTros->first()?->TenVaiTro ?? 'chưa gán vai trò';

            return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được tạo thành công với vai trò: '.$roleName);
        } catch (\Exception $e) {
            return redirect()->route('accounts.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function storeQuickEmployee(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'HoTen' => ['required', 'string', 'max:100'],
            'SoDienThoai' => [
                'required',
                'string',
                'max:15',
                'regex:/^0\d{9,10}$/',
                'unique:NhanVien,SoDienThoai',
            ],
            'ChucDanh' => ['nullable', 'string', 'max:100'],
        ], [
            'HoTen.required' => 'Họ và tên là bắt buộc.',
            'SoDienThoai.required' => 'Số điện thoại là bắt buộc.',
            'SoDienThoai.regex' => 'Số điện thoại không đúng định dạng (ví dụ: 0901234567).',
            'SoDienThoai.unique' => 'Số điện thoại đã được sử dụng cho hồ sơ nhân viên khác.',
        ]);

        $employee = NhanVien::query()->create([
            'HoTen' => $validated['HoTen'],
            'SoDienThoai' => $validated['SoDienThoai'],
            'ChucDanh' => $validated['ChucDanh'] ?? null,
            'TrangThai' => 'Hoạt động',
        ]);

        return response()->json([
            'employee' => [
                'NhanVienID' => $employee->getKey(),
                'HoTen' => $employee->HoTen,
                'SoDienThoai' => $employee->SoDienThoai,
            ],
        ], 201);
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

    public function createAvatarUploadUrl(Request $request): JsonResponse
    {
        $account = $request->user();

        $validated = $request->validate([
            'content_type' => ['required', 'in:image/jpeg,image/png,image/gif,image/webp'],
            'file_size' => ['required', 'integer', 'min:1', 'max:2097152'],
        ]);

        try {
            if (! $this->isSupabaseAvatarConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chức năng tải avatar lên Supabase chưa được cấu hình.',
                ], 503);
            }

            $path = $this->supabaseAvatarPath($account);
            $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
            $bucket = (string) config('services.supabase.avatar_bucket');
            $serviceRoleKey = (string) config('services.supabase.service_role_key');
            $signingUrl = $projectUrl.'/storage/v1/object/upload/sign/'
                .rawurlencode($bucket).'/'.$this->encodeStoragePath($path);

            $response = Http::withHeaders([
                'apikey' => $serviceRoleKey,
                'Authorization' => 'Bearer '.$serviceRoleKey,
            ])->acceptJson()->timeout(10)->post($signingUrl, ['upsert' => true]);

            if (! $response->successful()) {
                Log::error('Supabase avatar upload URL request failed.', [
                    'account_id' => $account->getKey(),
                    'status' => $response->status(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Không thể chuẩn bị tải avatar lên Supabase.',
                ], 502);
            }

            $signedUrl = $response->json('signedURL')
                ?? $response->json('signedUrl')
                ?? $response->json('url');
            $token = $response->json('token');

            if (! is_string($token) || $token === '') {
                if (is_string($signedUrl) && $signedUrl !== '') {
                    parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $query);
                    $token = $query['token'] ?? null;
                }
            }

            if (! is_string($token) || $token === '') {
                Log::error('Supabase avatar upload URL response did not contain a token.', [
                    'account_id' => $account->getKey(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Supabase không trả về mã tải avatar hợp lệ.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'path' => $path,
                'token' => $token,
            ]);
        } catch (Throwable $exception) {
            Log::error('Preparing direct Supabase avatar upload failed.', [
                'account_id' => $account->getKey(),
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể chuẩn bị tải avatar lên. Vui lòng thử lại.',
            ], 500);
        }
    }

    public function completeAvatarUpload(Request $request): JsonResponse
    {
        $account = $request->user();
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'in:image/jpeg,image/png,image/gif,image/webp'],
        ]);
        $path = $this->supabaseAvatarPath($account);

        if ($validated['path'] !== $path) {
            return response()->json([
                'success' => false,
                'message' => 'Đường dẫn avatar không hợp lệ.',
            ], 422);
        }

        try {
            $avatarUrl = $this->supabaseAvatarPublicUrl($path);
            $response = Http::timeout(10)->head($avatarUrl);
            $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $contentLength = (int) $response->header('Content-Length', 0);

            if (
                ! $response->successful()
                || $contentType !== $validated['content_type']
                || ($contentLength > 0 && $contentLength > 2 * 1024 * 1024)
            ) {
                Log::warning('Uploaded avatar could not be verified in Supabase Storage.', [
                    'account_id' => $account->getKey(),
                    'status' => $response->status(),
                    'content_type' => $contentType,
                    'content_length' => $contentLength,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Không xác minh được ảnh đã tải lên. Vui lòng thử lại.',
                ], 422);
            }

            $avatarUrl .= '?v='.now()->format('Uu');
            $account->AvatarURL = $avatarUrl;
            $account->save();
            $this->deleteLocalAvatarFiles($account);

            return response()->json([
                'success' => true,
                'avatar_url' => $avatarUrl,
            ]);
        } catch (Throwable $exception) {
            Log::error('Saving Supabase avatar URL failed.', [
                'account_id' => $account->getKey(),
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lưu URL avatar. Vui lòng thử lại.',
            ], 500);
        }
    }

    private function deleteAvatarFiles(User $account, ?string $exceptPath = null): void
    {
        $this->deleteLocalAvatarFiles($account, $exceptPath);

        if ($this->isSupabaseAvatarConfigured()) {
            $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
            $bucket = rawurlencode((string) config('services.supabase.avatar_bucket'));
            $path = $this->encodeStoragePath($this->supabaseAvatarPath($account));
            $serviceRoleKey = (string) config('services.supabase.service_role_key');
            $response = Http::withHeaders([
                'apikey' => $serviceRoleKey,
                'Authorization' => 'Bearer '.$serviceRoleKey,
            ])->timeout(10)->delete(
                $projectUrl.'/storage/v1/object/'.$bucket.'/'.$path,
            );

            if (! $response->successful()) {
                throw new \RuntimeException('Không thể xóa ảnh đại diện trên Supabase Storage.');
            }
        } elseif ($this->isSupabaseStorageConfigured()) {
            $deleted = Storage::disk('supabase')->delete($this->supabaseAvatarPath($account));

            if (! $deleted) {
                throw new \RuntimeException('Không thể xóa ảnh đại diện trên Supabase Storage.');
            }
        }

        $account->AvatarURL = null;
        $account->save();
    }

    private function deleteLocalAvatarFiles(User $account, ?string $exceptPath = null): void
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

    private function isSupabaseStorageConfigured(): bool
    {
        return collect([
            config('filesystems.disks.supabase.key'),
            config('filesystems.disks.supabase.secret'),
            config('filesystems.disks.supabase.bucket'),
            config('filesystems.disks.supabase.endpoint'),
            config('filesystems.disks.supabase.url'),
        ])->every(fn (?string $value): bool => filled($value));
    }

    private function isSupabaseAvatarConfigured(): bool
    {
        return collect([
            config('services.supabase.project_url'),
            config('services.supabase.service_role_key'),
            config('services.supabase.avatar_bucket'),
        ])->every(fn (?string $value): bool => filled($value));
    }

    private function supabaseAvatarPath(User $account): string
    {
        return 'avatars/'.$account->getKey();
    }

    private function supabaseAvatarPublicUrl(string $path): string
    {
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $bucket = rawurlencode((string) config('services.supabase.avatar_bucket'));
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        return $projectUrl.'/storage/v1/object/public/'.$bucket.'/'.$encodedPath;
    }

    private function encodeStoragePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
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
            && $user->isOwner();
    }
}
