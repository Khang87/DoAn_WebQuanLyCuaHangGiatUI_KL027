<?php

namespace App\Services;

use App\Models\BangGia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = BangGia::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('dichVu', fn ($s) => $s->where('TenDichVu', 'LIKE', "%{$search}%"))
                    ->orWhereHas('loaiDoGiat', fn ($g) => $g->where('TenLoaiDoGiat', 'LIKE', "%{$search}%"));
            });
        }

        if (! empty($filters['service_id'])) {
            $query->where('DichVuID', $filters['service_id']);
        }

        if (! empty($filters['garment_id'])) {
            $query->where('LoaiDoGiatID', $filters['garment_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $allowedSorts = ['BangGiaID', 'DonViTinhID', 'DonGia', 'NgayApDung', 'TrangThai'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'NgayApDung';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['dichVu', 'loaiDoGiat', 'donViTinh'])->orderBy($sortBy, $sortOrder)->paginate(10);
    }

    public function find(int $id): ?BangGia
    {
        return BangGia::with(['dichVu', 'loaiDoGiat', 'donViTinh'])->find($id);
    }

    public function create(array $data): BangGia
    {
        return DB::transaction(function () use ($data) {
            $this->lockPricingTuples([$data]);
            $this->ensurePeriodDoesNotOverlap($data);

            return BangGia::create($data);
        });
    }

    public function update(BangGia $pricing, array $data): BangGia
    {
        return DB::transaction(function () use ($pricing, $data): BangGia {
            $lockedPricing = BangGia::query()
                ->lockForUpdate()
                ->findOrFail($pricing->getKey());
            $updatedData = array_merge(
                $lockedPricing->only(['DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'NgayApDung', 'NgayKetThuc']),
                $data,
            );

            $this->lockPricingTuples([$lockedPricing->getAttributes(), $updatedData]);
            $this->ensurePeriodDoesNotOverlap($updatedData, (int) $lockedPricing->getKey());

            $lockedPricing->update($data);

            return $lockedPricing->fresh();
        });
    }

    public function delete(BangGia $pricing): bool
    {
        return $pricing->delete();
    }

    public function restore(int $id): ?BangGia
    {
        return DB::transaction(function () use ($id): ?BangGia {
            $pricing = BangGia::query()
                ->lockForUpdate()
                ->find($id);

            if ($pricing === null) {
                return null;
            }

            $data = $pricing->getAttributes();
            $this->lockPricingTuples([$data]);
            $this->ensurePeriodDoesNotOverlap($data, (int) $pricing->getKey());
            $pricing->update(['TrangThai' => 'Hoạt động']);

            return $pricing->fresh();
        });
    }

    public function getLatestPrice(int $serviceId, int $garmentId, ?int $unitId = null): ?float
    {
        return $this->getLatestPricing($serviceId, $garmentId, $unitId)?->DonGia;
    }

    /** The effective price is selected once, with the same ordering for every caller. */
    private function effectivePrices(): Builder
    {
        $date = today()->toDateString();

        return BangGia::query()
            ->where('TrangThai', 'Hoạt động')
            ->where(fn ($query) => $query->whereNull('NgayApDung')->orWhereDate('NgayApDung', '<=', $date))
            ->where(fn ($query) => $query->whereNull('NgayKetThuc')->orWhereDate('NgayKetThuc', '>=', $date))
            ->orderByRaw('CASE WHEN "NgayApDung" IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('NgayApDung')
            ->orderByDesc('BangGiaID');
    }

    /** Effective selectable prices, retaining every service/garment/unit tuple. */
    public function orderOptions(): Collection
    {
        return $this->effectivePrices()
            ->with('donViTinh')
            ->whereNotNull('DonGia')
            ->whereHas('donViTinh', fn ($query) => $query
                ->where('TrangThai', 'Hoạt động')
                ->where(fn ($labels) => $labels
                    ->where(fn ($label) => $label->whereNotNull('KyHieu')->where('KyHieu', '<>', ''))
                    ->orWhere(fn ($label) => $label->whereNotNull('TenDonViTinh')->where('TenDonViTinh', '<>', ''))))
            ->get()
            ->unique(fn ($price) => $price->DichVuID.':'.$price->LoaiDoGiatID.':'.$price->DonViTinhID)
            ->values();
    }

    public function getLatestPricing(int $serviceId, int $garmentId, ?int $unitId = null): ?BangGia
    {
        return $this->effectivePrices()
            ->where('DichVuID', $serviceId)
            ->where('LoaiDoGiatID', $garmentId)
            ->when($unitId !== null, fn ($query) => $query->where('DonViTinhID', $unitId))
            ->first();
    }

    /** @return array<string, BangGia> Prices for all requested tuples in one query. */
    public function getLatestPricingForTuples(array $tuples): array
    {
        if ($tuples === []) {
            return [];
        }

        $rows = $this->effectivePrices()
            ->where(function ($query) use ($tuples): void {
                foreach ($tuples as $tuple) {
                    $query->orWhere(fn ($q) => $q
                        ->where('DichVuID', $tuple['DichVuID'])
                        ->where('LoaiDoGiatID', $tuple['LoaiDoGiatID'])
                        ->where('DonViTinhID', $tuple['DonViTinhID']));
                }
            })->get();
        $prices = [];

        foreach ($rows as $row) {
            $key = $row->DichVuID.':'.$row->LoaiDoGiatID.':'.$row->DonViTinhID;
            $prices[$key] ??= $row;
        }

        return $prices;
    }

    public function hasOverlappingPeriod(
        int $serviceId,
        int $garmentId,
        int $unitId,
        string $startDate,
        ?string $endDate,
        ?int $exceptPricingId = null,
    ): bool {
        $query = BangGia::query()
            ->where('DichVuID', $serviceId)
            ->where('LoaiDoGiatID', $garmentId)
            ->where('DonViTinhID', $unitId)
            ->where(function ($query) use ($startDate): void {
                $query->whereNull('NgayKetThuc')
                    ->orWhereDate('NgayKetThuc', '>=', $startDate);
            });

        if ($endDate !== null) {
            $query->whereDate('NgayApDung', '<=', $endDate);
        }

        if ($exceptPricingId !== null) {
            $query->where('BangGiaID', '!=', $exceptPricingId);
        }

        return $query->exists();
    }

    /**
     * Prevent concurrent writes for the same price tuple from both passing
     * the overlap check on PostgreSQL.
     *
     * @param  array<int, array<string, mixed>>  $tuples
     */
    private function lockPricingTuples(array $tuples): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $keys = [];

        foreach ($tuples as $tuple) {
            $keys[] = implode(':', [
                $tuple['DichVuID'],
                $tuple['LoaiDoGiatID'],
                $tuple['DonViTinhID'],
            ]);
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        foreach ($keys as $key) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensurePeriodDoesNotOverlap(array $data, ?int $exceptPricingId = null): void
    {
        if (! $this->hasOverlappingPeriod(
            (int) $data['DichVuID'],
            (int) $data['LoaiDoGiatID'],
            (int) $data['DonViTinhID'],
            (string) $data['NgayApDung'],
            $data['NgayKetThuc'] ?? null,
            $exceptPricingId,
        )) {
            return;
        }

        throw ValidationException::withMessages([
            'NgayApDung' => 'Khoảng thời gian bảng giá bị chồng lấn với một bản giá khác của cùng dịch vụ, loại đồ giặt và đơn vị tính.',
        ]);
    }
}
