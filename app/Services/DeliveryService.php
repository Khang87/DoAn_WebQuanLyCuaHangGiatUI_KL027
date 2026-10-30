<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use App\Models\BookingKiemKe;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\NhanVien;
use App\Models\NhatKyHeThong;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(private OrderService $orderService) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = GiaoNhan::query();

        if (isset($filters['employee_id'])) {
            $query->where('NhanVienID', $filters['employee_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->whereHas('donHang', fn ($orderQuery) => $orderQuery->where('KhachHangID', $filters['customer_id']));
        }

        if (! empty($filters['method'])) {
            $method = match ($filters['method']) {
                'nhan_do' => 'NHAN_DO',
                'giao_do' => 'GIAO_DO',
                default => $filters['method'],
            };

            $query->where('LoaiGiaoNhan', $method);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'received') {
                $query->whereRaw('1 = 0');
            } else {
                $status = DeliveryStatus::parse($filters['status']);
                $query->where('TrangThai', $status->dbValue());
                if ($status === DeliveryStatus::Picking) {
                    $query->where('LoaiGiaoNhan', 'NHAN_DO');
                } elseif ($status === DeliveryStatus::Delivering) {
                    $query->where('LoaiGiaoNhan', 'GIAO_DO');
                }
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('GiaoNhanID', $numericPart);
                }
                $q->orWhere('DiaChi', 'LIKE', "%{$search}%")
                    ->orWhere('GhiChu', 'LIKE', "%{$search}%")
                    ->orWhereHas('donHang.khachHang', function ($sub) use ($search) {
                        $sub->where('HoTen', 'LIKE', "%{$search}%")
                            ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                    });
            });
        }

        $allowedSorts = ['GiaoNhanID', 'HinhThuc', 'TrangThai', 'ThoiGianDuKien'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'ThoiGianDuKien';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->with([
                'donHang:DonHangID,MaDonHang,KhachHangID',
                'donHang.khachHang:KhachHangID,HoTen,SoDienThoai',
                'nhanVien:NhanVienID,HoTen',
            ])
            ->orderBy($sortBy, $sortOrder)
            ->paginate(10)
            ->withQueryString();
    }

    public function getUnifiedDeliveries(array $filters = []): LengthAwarePaginator
    {
        $giaoNhanCreatedAtColumn = Schema::hasColumn('GiaoNhan', 'NgayTao') ? 'g.NgayTao' : null;
        $bookingIntakeCreatedAtColumn = Schema::hasColumn('BookingGiaoNhan', 'NgayTao') ? 'bg.NgayTao' : null;

        $operationalRows = DB::table('GiaoNhan as g')
            ->leftJoin('DonHang as d', 'd.DonHangID', '=', 'g.DonHangID')
            ->leftJoin('Booking as b', 'b.BookingID', '=', 'd.BookingID')
            ->leftJoin('KhachHang as c', 'c.KhachHangID', '=', 'd.KhachHangID')
            ->leftJoin('NhanVien as e', 'e.NhanVienID', '=', 'g.NhanVienID')
            ->selectRaw("'operational' as source_type")
            ->addSelect([
                'g.GiaoNhanID as source_id',
                'g.LoaiGiaoNhan as task_type',
                'c.KhachHangID as customer_id',
                'c.HoTen as customer_name',
                'c.SoDienThoai as customer_phone',
                'b.BookingID as booking_id',
                'b.MaBooking as booking_code',
                'd.DonHangID as order_id',
                'd.MaDonHang as order_code',
                'd.TrangThai as order_status',
                'g.NhanVienID as employee_id',
                'e.HoTen as employee_name',
                'g.ThoiGianDuKien as expected_at',
                'g.TrangThai as raw_status',
                'g.HinhThuc as fulfillment',
                'g.DiaChi as address',
            ])
            ->when($giaoNhanCreatedAtColumn !== null, fn ($query) => $query->addSelect([$giaoNhanCreatedAtColumn.' as created_at']))
            ->when($giaoNhanCreatedAtColumn === null, fn ($query) => $query->selectRaw('NULL as created_at'))
            ->selectRaw('0 as intake_mutable')
            ->selectRaw("'GN-' || CAST(\"g\".\"GiaoNhanID\" AS TEXT) as source_code")
            ->selectRaw(
                "CASE
                    WHEN \"g\".\"TrangThai\" = ? THEN 'pending'
                    WHEN \"g\".\"TrangThai\" = ? AND \"g\".\"LoaiGiaoNhan\" = 'NHAN_DO' THEN 'picking'
                    WHEN \"g\".\"TrangThai\" = ? AND \"g\".\"LoaiGiaoNhan\" = 'GIAO_DO' THEN 'delivering'
                    WHEN \"g\".\"TrangThai\" = ? THEN 'completed'
                    WHEN \"g\".\"TrangThai\" = ? THEN 'cancelled'
                    ELSE 'pending'
                END as status_key",
                ['Chờ thực hiện', 'Đang thực hiện', 'Đang thực hiện', 'Hoàn thành', 'Đã hủy'],
            )
            ->selectRaw('NULL as booking_status');

        // Older Booking conversions may contain a duplicate operational pickup.
        $operationalRows->where(function ($query): void {
            $query->where('g.LoaiGiaoNhan', '!=', 'NHAN_DO')
                ->orWhereNotExists(fn ($duplicate) => $duplicate->selectRaw('1')
                    ->from('BookingGiaoNhan as bg')
                    ->whereColumn('bg.DonHangID', 'g.DonHangID')
                    ->whereColumn('bg.LoaiGiaoNhan', 'g.LoaiGiaoNhan'));
        });

        $bookingRows = DB::table('BookingGiaoNhan as bg')
            ->leftJoin('Booking as b', 'b.BookingID', '=', 'bg.BookingID')
            ->leftJoin('DonHang as d', 'd.DonHangID', '=', 'bg.DonHangID')
            ->leftJoin('KhachHang as c', 'c.KhachHangID', '=', 'b.KhachHangID')
            ->leftJoin('NhanVien as e', 'e.NhanVienID', '=', 'bg.NhanVienID')
            ->selectRaw("'booking' as source_type")
            ->addSelect([
                'bg.BookingGiaoNhanID as source_id',
                'bg.LoaiGiaoNhan as task_type',
                'c.KhachHangID as customer_id',
                'c.HoTen as customer_name',
                'c.SoDienThoai as customer_phone',
                'b.BookingID as booking_id',
                'b.MaBooking as booking_code',
                'd.DonHangID as order_id',
                'd.MaDonHang as order_code',
                'd.TrangThai as order_status',
                'bg.NhanVienID as employee_id',
                'e.HoTen as employee_name',
                'bg.ThoiGianDuKien as expected_at',
                'bg.TrangThai as raw_status',
                'bg.HinhThuc as fulfillment',
                'bg.DiaChi as address',
            ])
            ->when($bookingIntakeCreatedAtColumn !== null, fn ($query) => $query->addSelect([$bookingIntakeCreatedAtColumn.' as created_at']))
            ->when($bookingIntakeCreatedAtColumn === null, fn ($query) => $query->selectRaw('NULL as created_at'))
            ->selectRaw(
                "CASE
                    WHEN \"bg\".\"LoaiGiaoNhan\" = 'NHAN_DO'
                        AND \"bg\".\"TrangThai\" IN (?, ?)
                        AND \"b\".\"TrangThai\" <> ?
                        AND \"bg\".\"ThoiGianThucTe\" IS NULL
                        AND \"bg\".\"DonHangID\" IS NULL
                        AND \"bg\".\"BookingKiemKeID\" IS NULL
                        AND NOT EXISTS (SELECT 1 FROM \"BookingKiemKe\" AS \"bi\" WHERE \"bi\".\"BookingID\" = \"bg\".\"BookingID\")
                        AND NOT EXISTS (SELECT 1 FROM \"DonHang\" AS \"bo\" WHERE \"bo\".\"BookingID\" = \"bg\".\"BookingID\")
                    THEN 1 ELSE 0
                END as intake_mutable",
                [
                    DeliveryStatus::Pending->dbValue(),
                    DeliveryStatus::Picking->dbValue(),
                    BookingStatus::Cancelled->value,
                ],
            )
            ->selectRaw("'BGN-' || CAST(\"bg\".\"BookingGiaoNhanID\" AS TEXT) as source_code")
            ->selectRaw(
                "CASE
                    WHEN \"bg\".\"LoaiGiaoNhan\" = 'NHAN_DO' AND \"bg\".\"TrangThai\" = ? THEN 'received'
                    WHEN \"bg\".\"TrangThai\" = ? THEN 'pending'
                    WHEN \"bg\".\"TrangThai\" = ? AND \"bg\".\"LoaiGiaoNhan\" = 'NHAN_DO' THEN 'picking'
                    WHEN \"bg\".\"TrangThai\" = ? AND \"bg\".\"LoaiGiaoNhan\" = 'GIAO_DO' THEN 'delivering'
                    WHEN \"bg\".\"TrangThai\" = ? THEN 'completed'
                    WHEN \"bg\".\"TrangThai\" = ? THEN 'cancelled'
                    ELSE 'pending'
                END as status_key",
                ['Đã nhận', 'Chờ thực hiện', 'Đang thực hiện', 'Đang thực hiện', 'Hoàn thành', 'Đã hủy'],
            )
            ->addSelect('b.TrangThai as booking_status');

        // Prefer the operational return slip while it is active; otherwise the Booking plan duplicates it.
        $bookingRows->whereNotExists(fn ($query) => $query->selectRaw('1')
            ->from('GiaoNhan as g')
            ->whereColumn('g.DonHangID', 'bg.DonHangID')
            ->whereColumn('g.LoaiGiaoNhan', 'bg.LoaiGiaoNhan')
            ->where('g.LoaiGiaoNhan', 'GIAO_DO')
            ->where('g.TrangThai', '!=', DeliveryStatus::Cancelled->dbValue()));

        $rows = $operationalRows->unionAll($bookingRows);
        $query = DB::query()->fromSub($rows, 'delivery_rows');

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }
        if (! empty($filters['method'])) {
            $method = match ($filters['method']) {
                'nhan_do' => 'NHAN_DO',
                'giao_do' => 'GIAO_DO',
                default => null,
            };
            if ($method !== null) {
                $query->where('task_type', $method);
            }
        }
        if (! empty($filters['status']) && in_array($filters['status'], [...DeliveryStatus::values(), 'received'], true)) {
            $query->where('status_key', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = mb_strtolower(trim($filters['search']));
            $pattern = '%'.$search.'%';
            $query->where(function ($searchQuery) use ($pattern, $search): void {
                foreach ([
                    'source_code',
                    'customer_name',
                    'customer_phone',
                    'booking_code',
                    'order_code',
                    'employee_name',
                    'fulfillment',
                    'address',
                    'task_type',
                ] as $column) {
                    $searchQuery->orWhereRaw('LOWER('.$column.') LIKE ?', [$pattern]);
                }

                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if ($numericPart !== '') {
                    $searchQuery->orWhere('source_id', (int) $numericPart)
                        ->orWhere('booking_id', (int) $numericPart)
                        ->orWhere('order_id', (int) $numericPart);
                }
            });
        }

        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $useCreatedAtSorting = Schema::hasColumn('GiaoNhan', 'NgayTao') && Schema::hasColumn('BookingGiaoNhan', 'NgayTao');

        $queryBuilder = $query
            ->when($useCreatedAtSorting, fn ($builder) => $builder
                ->orderByRaw('CASE WHEN created_at IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('created_at', $sortOrder), fn ($builder) => $builder
                ->orderByRaw('CASE WHEN expected_at IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('expected_at', $sortOrder));

        return $queryBuilder
            ->orderBy('source_type')
            ->orderBy('source_id')
            ->paginate(10)
            ->withQueryString();
    }

    public function getBookingDocuments(array $filters = []): LengthAwarePaginator
    {
        $query = BookingGiaoNhan::query()->with([
            'booking:BookingID,MaBooking,KhachHangID,TrangThai,HinhThucNhanDo,HinhThucTraDo',
            'booking.khachHang:KhachHangID,HoTen,SoDienThoai',
            'booking.donHangs:DonHangID,MaDonHang,BookingID,KhachHangID',
            'donHang:DonHangID,MaDonHang,KhachHangID',
            'donHang.khachHang:KhachHangID,HoTen,SoDienThoai',
            'nhanVien:NhanVienID,HoTen',
        ]);

        if (isset($filters['employee_id'])) {
            $query->where('NhanVienID', $filters['employee_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->whereHas('booking', fn ($bookingQuery) => $bookingQuery
                ->where('KhachHangID', $filters['customer_id']));
        }

        if (! empty($filters['method'])) {
            $method = match ($filters['method']) {
                'nhan_do' => 'NHAN_DO',
                'giao_do' => 'GIAO_DO',
                default => $filters['method'],
            };
            $query->where('LoaiGiaoNhan', $method);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'received') {
                $query->where('LoaiGiaoNhan', 'NHAN_DO')->where('TrangThai', 'Đã nhận');
            } else {
                $status = DeliveryStatus::parse($filters['status']);
                $query->where(function ($statusQuery) use ($status): void {
                    $statusQuery->where('TrangThai', $status->dbValue());
                    if ($status === DeliveryStatus::Completed) {
                        $statusQuery->orWhere(function ($receivedQuery): void {
                            $receivedQuery->where('LoaiGiaoNhan', 'NHAN_DO')
                                ->where('TrangThai', 'Đã nhận');
                        });
                    }
                });
                if ($status === DeliveryStatus::Picking) {
                    $query->where('LoaiGiaoNhan', 'NHAN_DO');
                } elseif ($status === DeliveryStatus::Delivering) {
                    $query->where('LoaiGiaoNhan', 'GIAO_DO');
                }
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $numericPart = preg_replace('/[^0-9]/', '', $search);
            $query->where(function ($searchQuery) use ($search, $numericPart): void {
                $searchQuery->where('DiaChi', 'LIKE', "%{$search}%");
                if ($numericPart !== '') {
                    $searchQuery->orWhere('BookingGiaoNhanID', $numericPart)
                        ->orWhere('BookingID', $numericPart)
                        ->orWhere('DonHangID', $numericPart);
                }
                $searchQuery->orWhere('LoaiGiaoNhan', 'LIKE', "%{$search}%")
                    ->orWhereHas('booking', function ($bookingQuery) use ($search): void {
                        $bookingQuery->where('MaBooking', 'LIKE', "%{$search}%")
                            ->orWhereHas('khachHang', fn ($customerQuery) => $customerQuery
                                ->where('HoTen', 'LIKE', "%{$search}%")
                                ->orWhere('SoDienThoai', 'LIKE', "%{$search}%"));
                    })
                    ->orWhereHas('donHang', fn ($orderQuery) => $orderQuery
                        ->where('MaDonHang', 'LIKE', "%{$search}%"));
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? null, ['BookingGiaoNhanID', 'HinhThuc', 'TrangThai', 'ThoiGianDuKien'], true)
            ? $filters['sort_by']
            : 'ThoiGianDuKien';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('BookingGiaoNhanID', $sortOrder)
            ->paginate(10, ['*'], 'booking_page')
            ->withQueryString();
    }

    public function findBookingDocument(int $id): ?BookingGiaoNhan
    {
        return BookingGiaoNhan::query()
            ->with([
                'booking.khachHang',
                'booking.nhanVienXacNhan',
                'booking.donHangs',
                'booking.kiemKe',
                'booking.giaoNhanDuKiens.nhanVien',
                'donHang.khachHang',
                'nhanVien',
            ])
            ->find($id);
    }

    public function canManageBookingIntake(BookingGiaoNhan $document): bool
    {
        if (
            $document->LoaiGiaoNhan !== 'NHAN_DO'
            || $document->booking?->statusEnum() === BookingStatus::Cancelled
            || ! in_array($document->TrangThai, [
                DeliveryStatus::Pending->dbValue(),
                DeliveryStatus::Picking->dbValue(),
            ], true)
            || $document->ThoiGianThucTe !== null
            || $document->DonHangID !== null
            || $document->BookingKiemKeID !== null
        ) {
            return false;
        }

        return ! BookingKiemKe::query()->where('BookingID', $document->BookingID)->exists()
            && ! DonHang::query()->where('BookingID', $document->BookingID)->exists();
    }

    /**
     * @param  array{employee_id?: int, pickup_date?: ?string, pickup_time?: ?string, address?: ?string}  $data
     */
    public function updateBookingIntake(BookingGiaoNhan $document, array $data): BookingGiaoNhan
    {
        return DB::transaction(function () use ($document, $data): BookingGiaoNhan {
            $current = $this->lockMutableBookingIntake($document->getKey());
            $attributes = [];

            if (array_key_exists('employee_id', $data)) {
                EmployeeAssignment::assertBookingIntakeAssignable(
                    auth()->user(),
                    isset($data['employee_id']) ? (int) $data['employee_id'] : null,
                    'employee_id',
                    $current->NhanVienID,
                );
                $attributes['NhanVienID'] = (int) $data['employee_id'];
            }
            if (array_key_exists('pickup_date', $data) || array_key_exists('pickup_time', $data)) {
                $attributes['ThoiGianDuKien'] = empty($data['pickup_date']) || empty($data['pickup_time'])
                    ? null
                    : $data['pickup_date'].' '.$data['pickup_time'].':00';
            }
            if (array_key_exists('address', $data)) {
                $attributes['DiaChi'] = $current->HinhThuc === 'Tại nhà' ? $data['address'] : null;
            }

            if ($attributes !== []) {
                $previousEmployeeId = $current->NhanVienID;
                $current->update($attributes);
                if (
                    array_key_exists('NhanVienID', $attributes)
                    && (int) $previousEmployeeId !== (int) $current->NhanVienID
                ) {
                    NhatKyHeThong::create([
                        'TaiKhoanID' => auth()->id(),
                        'HanhDong' => 'Phân công phiếu nhận Booking',
                        'BangDuLieu' => 'BookingGiaoNhan',
                        'BanGhiID' => $current->BookingGiaoNhanID,
                        'DuLieuCu' => ['NhanVienID' => $previousEmployeeId],
                        'DuLieuMoi' => ['NhanVienID' => $current->NhanVienID],
                        'LyDo' => null,
                        'ThoiGian' => now(),
                        'IPAddress' => request()->ip(),
                        'UserAgent' => request()->userAgent(),
                    ]);
                }
            }

            return $current->fresh(['booking', 'donHang', 'nhanVien']);
        });
    }

    private function lockMutableBookingIntake(int $id): BookingGiaoNhan
    {
        $bookingId = BookingGiaoNhan::query()->whereKey($id)->value('BookingID');
        if ($bookingId === null) {
            throw ValidationException::withMessages(['booking_delivery' => 'Không tìm thấy phiếu nhận đồ.']);
        }

        Booking::query()->lockForUpdate()->findOrFail($bookingId);
        $current = BookingGiaoNhan::query()->lockForUpdate()->findOrFail($id);

        if ((int) $current->BookingID !== (int) $bookingId || ! $this->canManageBookingIntake($current)) {
            throw ValidationException::withMessages([
                'booking_delivery' => 'Chỉ có thể sửa hoặc hủy phiếu nhận đồ đang chờ, chưa nhận, chưa kiểm kê và chưa tạo đơn.',
            ]);
        }

        return $current;
    }

    public function find(int $id): ?GiaoNhan
    {
        $relations = ['donHang.khachHang', 'nhanVien'];
        if (config('giatui.booking_two_step')) {
            $relations = array_merge($relations, [
                'donHang.booking.kiemKe',
                'donHang.booking.giaoNhanDuKiens',
                'donHang.chiTietDonHangs.dichVu',
                'donHang.chiTietDonHangs.loaiDoGiat',
                'donHang.chiTietDonHangs.donViTinh',
            ]);
        }

        return GiaoNhan::with($relations)->find($id);
    }

    public function create(array $data): GiaoNhan
    {
        return DB::transaction(function () use ($data): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($data['order_id'] ?? null);
            DeliveryRules::assertMutableOrder($order);

            if ($order->BookingID !== null && strtolower((string) ($data['method'] ?? '')) === 'giao_do') {
                $existingReturns = GiaoNhan::query()
                    ->where('DonHangID', $order->DonHangID)
                    ->where('LoaiGiaoNhan', 'GIAO_DO')
                    ->where('TrangThai', '!=', DeliveryStatus::Cancelled->dbValue())
                    ->lockForUpdate()
                    ->get();

                if ($existingReturns->count() > 1) {
                    throw ValidationException::withMessages([
                        'method' => 'Đơn hàng đã có nhiều phiếu giao còn hiệu lực; cần đối soát trước khi tạo thêm.',
                    ]);
                }

                if ($existingReturns->isNotEmpty()) {
                    $existingReturn = $existingReturns->first();
                    $expectedFulfillment = $order->booking()->value('HinhThucTraDo');
                    if ($existingReturn->HinhThuc !== $expectedFulfillment) {
                        throw ValidationException::withMessages([
                            'method' => 'Phiếu giao trả hiện có không khớp với hình thức đã chốt; cần đối soát trước khi tiếp tục.',
                        ]);
                    }

                    return $existingReturn;
                }
            }

            $data = DeliveryRules::validate($data, $order);
            $attributes = $this->mapAttributes($data);
            if ($order->BookingID !== null && $attributes['LoaiGiaoNhan'] === 'GIAO_DO') {
                $returnPlan = BookingGiaoNhan::query()
                    ->where('BookingID', $order->BookingID)
                    ->where('DonHangID', $order->DonHangID)
                    ->where('LoaiGiaoNhan', 'GIAO_DO')
                    ->lockForUpdate()
                    ->first();

                if ($returnPlan === null || $returnPlan->HinhThuc !== $attributes['HinhThuc']) {
                    throw ValidationException::withMessages([
                        'booking' => 'Không tìm thấy kế hoạch trả đồ hợp lệ đã liên kết với đơn hàng.',
                    ]);
                }

                $attributes['PhiGiaoNhan'] = $returnPlan->PhiGiaoNhan;
            }
            $this->assertNoConflictingLeg($attributes);
            if (Schema::hasColumn('GiaoNhan', 'NgayTao')) {
                $attributes['NgayTao'] ??= now();
            }

            return GiaoNhan::create($attributes);
        });
    }

    public function update(GiaoNhan $delivery, array $data): GiaoNhan
    {
        return DB::transaction(function () use ($delivery, $data): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());

            if (
                $current->LoaiGiaoNhan === 'GIAO_DO'
                && ! $this->isEditableReturnDelivery($order, $current)
            ) {
                throw ValidationException::withMessages([
                    'delivery' => 'Lịch giao đã hoàn thành, đã hủy hoặc không còn đủ điều kiện để chỉnh sửa.',
                ]);
            }

            return $this->updateLocked($order, $current, $data);
        });
    }

    public function advanceProgress(GiaoNhan $delivery): GiaoNhan
    {
        return DB::transaction(function () use ($delivery): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
            DeliveryRules::assertMutableOrder($order);
            if ($current->DonHangID !== $order->DonHangID) {
                throw ValidationException::withMessages(['order_id' => 'Phiếu đã thay đổi, vui lòng tải lại.']);
            }

            $currentStatus = DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan);
            $nextStatus = match (true) {
                $currentStatus === DeliveryStatus::Pending && $current->LoaiGiaoNhan === 'NHAN_DO' => DeliveryStatus::Picking,
                $currentStatus === DeliveryStatus::Pending && $current->LoaiGiaoNhan === 'GIAO_DO' && $current->HinhThuc === 'Tại cửa hàng' => DeliveryStatus::Completed,
                $currentStatus === DeliveryStatus::Pending && $current->LoaiGiaoNhan === 'GIAO_DO' => DeliveryStatus::Delivering,
                $currentStatus === DeliveryStatus::Picking && $current->LoaiGiaoNhan === 'NHAN_DO' => DeliveryStatus::Completed,
                $currentStatus === DeliveryStatus::Delivering && $current->LoaiGiaoNhan === 'GIAO_DO' => DeliveryStatus::Completed,
                default => throw ValidationException::withMessages(['status' => 'Phiếu giao nhận không còn tiến độ nào cần xác nhận.']),
            };

            return $this->updateLocked($order, $current, ['status' => $nextStatus->value]);
        });
    }

    public function advanceBookingDelivery(BookingGiaoNhan $delivery): BookingGiaoNhan
    {
        return DB::transaction(function () use ($delivery): BookingGiaoNhan {
            $current = BookingGiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
            $booking = Booking::query()->lockForUpdate()->findOrFail($current->BookingID);
            $order = DonHang::query()->lockForUpdate()->findOrFail($current->DonHangID);
            DeliveryRules::assertMutableOrder($order);

            if ($order->BookingID !== $booking->BookingID
                || $current->LoaiGiaoNhan !== 'GIAO_DO'
                || $current->HinhThuc !== 'Tại nhà'
                || $booking->HinhThucTraDo !== $current->HinhThuc
                || $current->NhanVienID === null
                || ! NhanVien::query()->whereKey($current->NhanVienID)->where('TrangThai', 'Hoạt động')->exists()) {
                throw ValidationException::withMessages(['delivery' => 'Phiếu giao đồ không còn hợp lệ hoặc không được phân công cho nhân viên đang hoạt động.']);
            }

            $status = DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan);
            if ($status === DeliveryStatus::Pending
                && in_array($order->statusEnum(), [OrderStatus::Washed, OrderStatus::Delivering, OrderStatus::Paid], true)) {
                $current->update(['TrangThai' => DeliveryStatus::Delivering->dbValue()]);
                if ($order->statusEnum() !== OrderStatus::Delivering) {
                    $order->update(['TrangThai' => OrderStatus::Delivering->value, 'NgayCapNhat' => now()]);
                }
            } elseif ($status === DeliveryStatus::Delivering
                && in_array($order->statusEnum(), [OrderStatus::Delivering, OrderStatus::Delivered], true)) {
                $current->update([
                    'TrangThai' => DeliveryStatus::Completed->dbValue(),
                    'ThoiGianThucTe' => now(),
                ]);
                if ($order->statusEnum() !== OrderStatus::Delivered) {
                    $order->update(['TrangThai' => OrderStatus::Delivered->value, 'NgayCapNhat' => now()]);
                }
                if ($booking->statusEnum() !== BookingStatus::Completed) {
                    $booking->update(['TrangThai' => BookingStatus::Completed->value]);
                }
            } else {
                throw ValidationException::withMessages(['status' => 'Phiếu giao nhận không còn tiến độ nào cần xác nhận.']);
            }

            return $current->fresh();
        });
    }

    public function canCancelReturnDelivery(GiaoNhan $delivery): bool
    {
        $order = $delivery->relationLoaded('donHang')
            ? $delivery->donHang
            : $delivery->donHang()->with('booking')->first();

        if (! $order) {
            return false;
        }

        $order->loadMissing('booking');

        return $this->isCancellableReturnDelivery($order, $delivery);
    }

    public function canEditReturnDelivery(GiaoNhan $delivery): bool
    {
        $order = $delivery->relationLoaded('donHang')
            ? $delivery->donHang
            : $delivery->donHang()->with('booking')->first();

        if (! $order) {
            return false;
        }

        $order->loadMissing('booking');

        return $this->isEditableReturnDelivery($order, $delivery);
    }

    public function cancelReturnDelivery(GiaoNhan $delivery): GiaoNhan
    {
        return DB::transaction(function () use ($delivery): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
            $order->loadMissing('booking');
            DeliveryRules::assertMutableOrder($order);

            if (
                $current->DonHangID !== $order->DonHangID
                || ! $this->isCancellableReturnDelivery($order, $current)
            ) {
                throw ValidationException::withMessages([
                    'delivery' => 'Chỉ có thể hủy lịch giao đồ chưa hoàn thành và còn hiệu lực.',
                ]);
            }

            $current->update(['TrangThai' => DeliveryStatus::Cancelled->dbValue()]);

            return $current->fresh();
        });
    }

    public function delete(GiaoNhan $delivery): bool
    {
        return DB::transaction(function () use ($delivery): bool {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
            DeliveryRules::assertMutableOrder($order);
            if ($current->LoaiGiaoNhan === 'GIAO_DO') {
                throw ValidationException::withMessages([
                    'delivery' => 'Không thể xóa phiếu giao đồ; hãy dùng thao tác hủy lịch giao để giữ lịch sử.',
                ]);
            }
            if (
                $current->DonHangID !== $order->DonHangID
                || DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan) === DeliveryStatus::Completed
            ) {
                throw ValidationException::withMessages([
                    'delivery' => 'Không thể xóa phiếu đã hoàn thành hoặc lần giao đã hủy cần được giữ lại lịch sử.',
                ]);
            }

            return $current->delete();
        });
    }

    private function assertNoConflictingLeg(array $attributes, ?int $exceptId = null): void
    {
        if ($attributes['TrangThai'] === DeliveryStatus::Cancelled->dbValue()) {
            return;
        }
        if (GiaoNhan::query()->where('DonHangID', $attributes['DonHangID'])
            ->where('LoaiGiaoNhan', $attributes['LoaiGiaoNhan'])
            ->where('TrangThai', '!=', DeliveryStatus::Cancelled->dbValue())
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            throw ValidationException::withMessages(['method' => 'Đơn hàng đã có phiếu còn hiệu lực cho chặng này.']);
        }
    }

    private function isEditableReturnDelivery(DonHang $order, GiaoNhan $delivery): bool
    {
        return $delivery->LoaiGiaoNhan === 'GIAO_DO'
            && ! in_array(
                DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan),
                [DeliveryStatus::Completed, DeliveryStatus::Cancelled],
                true,
            )
            && $order->statusEnum() !== OrderStatus::Cancelled
            && $order->booking?->statusEnum() !== BookingStatus::Cancelled;
    }

    private function isCancellableReturnDelivery(DonHang $order, GiaoNhan $delivery): bool
    {
        return $this->isEditableReturnDelivery($order, $delivery)
            && in_array($order->statusEnum(), [OrderStatus::Washed, OrderStatus::Delivering], true);
    }

    private function updateLocked(DonHang $order, GiaoNhan $current, array $data): GiaoNhan
    {
        DeliveryRules::assertMutableOrder($order);
        if ($current->DonHangID !== $order->DonHangID) {
            throw ValidationException::withMessages(['order_id' => 'Phiếu đã thay đổi, vui lòng tải lại.']);
        }
        $validated = DeliveryRules::validate($data, $order, $current);
        $this->assertNoConflictingLeg($this->mapAttributes($validated), $current->getKey());
        $attributes = $this->mapAttributes(array_intersect_key($validated, $data));
        if ($current->ThoiGianDuKien?->format('Y-m-d H:i') === ($validated['pickup_date'].' '.$validated['pickup_time'])) {
            unset($attributes['ThoiGianDuKien']);
        }
        $previousStatus = DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan);
        if (
            $previousStatus !== DeliveryStatus::Completed
            && DeliveryStatus::parseForLeg($validated['status'], $current->LoaiGiaoNhan) === DeliveryStatus::Completed
        ) {
            $attributes['ThoiGianThucTe'] = now();
        }
        $current->update($attributes);
        $targetStatus = DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan);

        if (
            $current->LoaiGiaoNhan === 'GIAO_DO'
            && $targetStatus === DeliveryStatus::Completed
            && $order->BookingID !== null
        ) {
            $bookingReturnPlan = BookingGiaoNhan::query()
                ->where('BookingID', $order->BookingID)
                ->where('DonHangID', $order->DonHangID)
                ->where('LoaiGiaoNhan', 'GIAO_DO')
                ->lockForUpdate()
                ->first();
            $bookingReturnPlan?->update([
                'NhanVienID' => $current->NhanVienID,
                'HinhThuc' => $current->HinhThuc,
                'DiaChi' => $current->DiaChi,
                'ThoiGianDuKien' => $current->ThoiGianDuKien,
                'ThoiGianThucTe' => $current->ThoiGianThucTe,
                'TrangThai' => $current->TrangThai,
            ]);
        }

        if ($current->LoaiGiaoNhan === 'GIAO_DO' && $previousStatus !== $targetStatus) {
            if ($targetStatus === DeliveryStatus::Delivering) {
                $this->orderService->syncStatusFromDelivery($order, $current, OrderStatus::Delivering);
            } elseif ($targetStatus === DeliveryStatus::Completed) {
                $this->orderService->syncStatusFromDelivery($order, $current, OrderStatus::Delivered);
            }
        }

        return $current->fresh();
    }

    private function mapAttributes(array $data): array
    {
        $attributes = [];

        if (array_key_exists('order_id', $data)) {
            $attributes['DonHangID'] = $data['order_id'];
        }

        if (array_key_exists('employee_id', $data)) {
            $attributes['NhanVienID'] = empty($data['employee_id']) ? null : (int) $data['employee_id'];
        }

        if (array_key_exists('method', $data)) {
            $attributes['LoaiGiaoNhan'] = match ($data['method']) {
                'nhan_do' => 'NHAN_DO',
                'giao_do' => 'GIAO_DO',
                default => throw new \InvalidArgumentException('Phương thức giao nhận không hợp lệ.'),
            };
        }

        if (array_key_exists('address', $data)) {
            $attributes['DiaChi'] = $data['address'];
        }

        if (array_key_exists('fulfillment', $data)) {
            $attributes['HinhThuc'] = $data['fulfillment'];
            if ($data['fulfillment'] === 'Tại cửa hàng') {
                $attributes['DiaChi'] = null;
            }
        }

        if (array_key_exists('pickup_date', $data) && array_key_exists('pickup_time', $data)) {
            $attributes['ThoiGianDuKien'] = empty($data['pickup_date']) ? null : $data['pickup_date'].' '.$data['pickup_time'];
        }

        if (array_key_exists('status', $data)) {
            $attributes['TrangThai'] = DeliveryStatus::parse($data['status'])->dbValue();

        }

        if (array_key_exists('notes', $data)) {
            $attributes['GhiChu'] = $data['notes'];
        }

        return $attributes;
    }
}
