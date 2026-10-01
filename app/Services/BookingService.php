<?php

namespace App\Services;

use App\Enums\BookingMethod;
use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Booking::query();

        if (! empty($filters['customer_id'])) {
            $query->where('KhachHangID', $filters['customer_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('HinhThucNhanDo', $filters['method']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('BookingID', $numericPart);
                }
                $q->orWhere('MaBooking', 'LIKE', "%{$search}%");
                $q->orWhere('DiaChiNhan', 'LIKE', "%{$search}%");
                $q->orWhereHas('khachHang', function ($sub) use ($search) {
                    $sub->where('HoTen', 'LIKE', "%{$search}%")
                        ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                });
            });
        }

        $allowedSorts = ['BookingID', 'MaBooking', 'KhachHangID', 'HinhThucNhanDo', 'TrangThai', 'NgayHen', 'NgayTao'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'NgayTao';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['khachHang', 'nhanVien', 'donHangs'])
            ->orderBy($sortBy, $sortOrder)
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): ?Booking
    {
        return Booking::query()
            ->with(['khachHang', 'dichVu', 'loaiDoGiat', 'donViTinh', 'nhanVien', 'donHangs'])
            ->find($id);
    }

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function update(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->BookingID);

            if (
                ($data['status'] ?? null) === BookingStatus::Cancelled->value
                && $lockedBooking->TrangThai !== BookingStatus::Cancelled->value
                && $this->hasUncancelledOrder($lockedBooking)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể hủy riêng đặt lịch đã có đơn hàng. Hãy xử lý đơn hàng liên kết trước.',
                ]);
            }

            $mappedData = $this->mapRequestData($data);
            $snapshotData = $this->resolveServiceSnapshot($data, $lockedBooking);
            $lockedBooking->update(array_merge($mappedData, $snapshotData));
            $lockedBooking->refresh();

            return $lockedBooking->fresh(['khachHang', 'donHangs']);
        });
    }

    /**
     * Form quản trị gửi tên field tiếng Anh, cột trong bảng lại là tiếng Việt
     * nên phải đổi tên trước khi ghi xuống model.
     */
    private function mapRequestData(array $data): array
    {
        $map = [
            'customer_id' => 'KhachHangID',
            'staff_id' => 'NhanVienID',
            'method' => 'HinhThucNhanDo',
            'scheduled_date' => 'NgayHen',
            'scheduled_time' => 'GioHen',
            'address' => 'DiaChiNhan',
            'notes' => 'GhiChu',
            'status' => 'TrangThai',
        ];

        $mapped = [];

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $mapped[$column] = $data[$key];
            }
        }

        return $mapped;
    }

    public function delete(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->BookingID);

            if ($this->hasConvertedOrder($lockedBooking)) {
                return false;
            }

            return (bool) $lockedBooking->delete();
        });
    }

    /**
     * Xác nhận đặt lịch và chuyển thành đơn hàng.
     *
     * Hàm này là idempotent: nếu đặt lịch đã có đơn (orders.booking_id) thì trả
     * lại đơn cũ thay vì tạo thêm, nên bấm "Xác nhận" nhiều lần cũng không sinh
     * đơn trùng. Toàn bộ việc tạo đơn + tạo phiếu giao chạy trong một
     * transaction nên thất bại giữa chừng sẽ rollback trọn vẹn.
     */
    public function confirmAndCreateOrder(Booking $booking): ?DonHang
    {
        $existing = $this->findOrderForBooking($booking);

        if ($existing) {
            return $existing;
        }

        if (! $booking->isConvertibleToOrder()) {
            return null;
        }

        return DB::transaction(function () use ($booking): DonHang {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->BookingID);
            $existing = $this->findOrderForBooking($lockedBooking);

            if ($existing) {
                return $existing;
            }

            if (! $lockedBooking->isConvertibleToOrder()) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ đặt lịch đã xác nhận mới có thể tạo đơn hàng.',
                ]);
            }

            return $this->insertOrderAndDelivery($lockedBooking)->fresh();
        });
    }

    /**
     * Insert bản ghi đơn hàng (kèm phiếu giao) có tham chiếu về lịch hẹn.
     * Bản ghi đơn luôn chứa mã tham chiếu của lịch đặt: qua quan hệ
     * `orders.booking_id` và qua mã ghi trong phần ghi chú.
     */
    private function insertOrderAndDelivery(Booking $booking): DonHang
    {
        $snapshot = $this->serviceSnapshotForOrder($booking);
        $bookingCode = $booking->MaBooking ?: Booking::nextCode();
        $total = $snapshot['ThanhTien'] ?? 0;

        $order = DonHang::create([
            'MaDonHang' => 'TMP'.Str::ulid(),
            'KhachHangID' => $booking->KhachHangID,
            'NhanVienID' => $booking->NhanVienID,
            'BookingID' => $booking->BookingID,
            'TrangThai' => OrderStatus::Pending->value,
            'GhiChu' => $this->buildOrderNotes($booking, $bookingCode),
            'TongTien' => $total,
            'ThanhTien' => $total,
        ]);
        $order->update([
            'MaDonHang' => 'DH'.str_pad((string) $order->DonHangID, 3, '0', STR_PAD_LEFT),
        ]);

        if ($snapshot !== null) {
            ChiTietDonHang::create(array_merge(
                ['DonHangID' => $order->DonHangID],
                $snapshot,
            ));
        }

        GiaoNhan::create([
            'DonHangID' => $order->DonHangID,
            'NhanVienID' => $booking->NhanVienID,
            'HinhThuc' => $booking->HinhThucNhanDo,
            'LoaiGiaoNhan' => $booking->methodEnum()->deliveryType(),
            'DiaChi' => $booking->methodEnum() === BookingMethod::GiaoDo
                ? $booking->DiaChiNhan
                : null,
            'ThoiGianDuKien' => $booking->NgayHen->format('Y-m-d').' '.$booking->GioHen->format('H:i:s'),
            'TrangThai' => DeliveryStatus::Pending->dbValue(),
            'GhiChu' => $booking->GhiChu,
        ]);

        return $order;
    }

    /**
     * Ghi chú của đơn luôn mở đầu bằng mã tham chiếu lịch đặt để nhân viên
     * nhìn bảng đơn là biết đơn này sinh ra từ lịch nào.
     */
    private function buildOrderNotes(Booking $booking, string $bookingCode): string
    {
        $reference = 'Tự động tạo từ đặt lịch '.$bookingCode
            .' ('.$booking->method_label.' ngày '
            .($booking->NgayHen?->format('d/m/Y') ?? '—').')';

        return mb_substr($booking->GhiChu
            ? $reference.' | '.$booking->GhiChu
            : $reference, 0, 500);
    }

    /**
     * Đặt lịch này đã có đơn hàng được tạo từ trước chưa?
     */
    public function hasConvertedOrder(Booking $booking): bool
    {
        return $this->findOrderForBooking($booking) !== null;
    }

    /**
     * Đơn hàng đã được tạo từ đặt lịch này (nếu có).
     */
    private function findOrderForBooking(Booking $booking): ?DonHang
    {
        return DonHang::where('BookingID', $booking->BookingID)->first();
    }

    private function hasUncancelledOrder(Booking $booking): bool
    {
        return DonHang::query()
            ->where('BookingID', $booking->BookingID)
            ->where('TrangThai', '!=', OrderStatus::Cancelled->value)
            ->exists();
    }

    /**
     * Reprice a booking snapshot from the currently effective price table.
     *
     * @return array<string, mixed>
     */
    private function resolveServiceSnapshot(array $data, Booking $booking): array
    {
        $fieldMap = [
            'service_id' => 'DichVuID',
            'garment_id' => 'LoaiDoGiatID',
            'unit_id' => 'DonViTinhID',
            'quantity' => 'SoLuong',
            'weight' => 'KhoiLuong',
        ];
        $hasSnapshotInput = collect(array_keys($fieldMap))
            ->contains(fn (string $key): bool => array_key_exists($key, $data));

        if (! $hasSnapshotInput) {
            return [];
        }

        $values = [];
        foreach ($fieldMap as $input => $column) {
            $values[$column] = array_key_exists($input, $data) ? $data[$input] : $booking->{$column};
        }

        $allEmpty = collect($values)->every(fn (mixed $value): bool => $value === null || $value === '');
        if ($allEmpty) {
            return [
                'DichVuID' => null,
                'LoaiDoGiatID' => null,
                'DonViTinhID' => null,
                'SoLuong' => null,
                'KhoiLuong' => null,
                'DonGia' => null,
                'ThanhTien' => null,
            ];
        }

        $serviceSelectionUnchanged = (int) $values['DichVuID'] === (int) $booking->DichVuID
            && (int) $values['LoaiDoGiatID'] === (int) $booking->LoaiDoGiatID
            && (int) $values['DonViTinhID'] === (int) $booking->DonViTinhID;
        $quantityUnchanged = ($values['SoLuong'] === null || $values['SoLuong'] === '')
            ? $booking->SoLuong === null
            : $booking->SoLuong !== null && (float) $values['SoLuong'] === (float) $booking->SoLuong;
        $weightUnchanged = (
            $values['KhoiLuong'] === null || $values['KhoiLuong'] === ''
                ? $booking->KhoiLuong === null
                : $booking->KhoiLuong !== null && (float) $values['KhoiLuong'] === (float) $booking->KhoiLuong
        );
        $unchangedSnapshot = $serviceSelectionUnchanged && $quantityUnchanged && $weightUnchanged;

        if ($unchangedSnapshot) {
            return [];
        }

        if (
            empty($values['DichVuID'])
            || empty($values['LoaiDoGiatID'])
            || empty($values['DonViTinhID'])
        ) {
            throw ValidationException::withMessages([
                'service_id' => 'Hãy chọn đầy đủ dịch vụ, loại đồ giặt và đơn vị tính.',
            ]);
        }

        $pricing = BangGia::getLatestPricing(
            (int) $values['DichVuID'],
            (int) $values['LoaiDoGiatID'],
            (int) $values['DonViTinhID'],
        );

        if (! $pricing) {
            throw ValidationException::withMessages([
                'garment_id' => 'Cặp dịch vụ và loại đồ giặt chưa có bảng giá đang hiệu lực cho đơn vị tính này.',
            ]);
        }

        $isWeightUnit = BangGia::isWeightUnit($pricing->unit);
        $quantity = $values['SoLuong'] !== null && $values['SoLuong'] !== '' ? (float) $values['SoLuong'] : null;
        $weight = $values['KhoiLuong'] !== null && $values['KhoiLuong'] !== '' ? (float) $values['KhoiLuong'] : null;

        if (
            ($isWeightUnit && ($weight === null || $weight <= 0 || $quantity !== null))
            || (! $isWeightUnit && ($quantity === null || $quantity <= 0 || $weight !== null))
        ) {
            throw ValidationException::withMessages([
                $isWeightUnit ? 'weight' : 'quantity' => $isWeightUnit
                    ? 'Vui lòng nhập khối lượng lớn hơn 0 và không nhập số lượng.'
                    : 'Vui lòng nhập số lượng lớn hơn 0 và không nhập khối lượng.',
            ]);
        }

        $billableAmount = $isWeightUnit
            ? max($weight, (float) config('giatui.khoi_luong_toi_thieu', 3.0))
            : $quantity;

        return [
            'DichVuID' => (int) $values['DichVuID'],
            'LoaiDoGiatID' => (int) $values['LoaiDoGiatID'],
            'DonViTinhID' => (int) $values['DonViTinhID'],
            'SoLuong' => $quantity,
            'KhoiLuong' => $weight,
            'DonGia' => (float) $pricing->DonGia,
            'ThanhTien' => round((float) $pricing->DonGia * $billableAmount, 2),
        ];
    }

    /**
     * Return the one optional service row stored in the Booking snapshot.
     *
     * @return array<string, mixed>|null
     */
    private function serviceSnapshotForOrder(Booking $booking): ?array
    {
        $hasNoSnapshot = $booking->DichVuID === null
            && $booking->LoaiDoGiatID === null
            && $booking->DonViTinhID === null
            && $booking->DonGia === null
            && $booking->ThanhTien === null
            && $booking->SoLuong === null
            && $booking->KhoiLuong === null;

        if ($hasNoSnapshot) {
            throw ValidationException::withMessages([
                'service_id' => 'Đặt lịch chưa có dịch vụ và loại đồ giặt. Hãy bổ sung thông tin trước khi tạo đơn hàng.',
            ]);
        }

        $quantity = $booking->SoLuong !== null ? (float) $booking->SoLuong : null;
        $weight = $booking->KhoiLuong !== null ? (float) $booking->KhoiLuong : null;
        $unit = $booking->donViTinh;

        if (! $unit) {
            throw ValidationException::withMessages([
                'service_id' => 'Đơn vị tính của dịch vụ trong đặt lịch không còn hợp lệ.',
            ]);
        }

        $isWeightUnit = $unit->isWeightUnit();

        if (
            $booking->DichVuID === null
            || $booking->LoaiDoGiatID === null
            || $booking->DonViTinhID === null
            || $booking->DonGia === null
            || $booking->ThanhTien === null
            || (($quantity === null) === ($weight === null))
            || ($quantity !== null && $quantity <= 0)
            || ($weight !== null && $weight <= 0)
            || ($isWeightUnit && ($quantity !== null || $weight === null))
            || (! $isWeightUnit && ($quantity === null || $weight !== null))
            || (float) $booking->DonGia < 0
            || (float) $booking->ThanhTien < 0
        ) {
            throw ValidationException::withMessages([
                'service_id' => 'Thông tin dịch vụ trong đặt lịch chưa đầy đủ hoặc không hợp lệ; hãy cập nhật trước khi tạo đơn.',
            ]);
        }

        return [
            'DichVuID' => $booking->DichVuID,
            'LoaiDoGiatID' => $booking->LoaiDoGiatID,
            'DonViTinhID' => $booking->DonViTinhID,
            'SoLuong' => $quantity,
            'KhoiLuong' => $weight,
            'DonGia' => (float) $booking->DonGia,
            'ThanhTien' => (float) $booking->ThanhTien,
            'GhiChu' => $booking->GhiChu,
        ];
    }
}
