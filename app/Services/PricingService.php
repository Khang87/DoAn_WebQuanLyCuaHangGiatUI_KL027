<?php

namespace App\Services;

use App\Models\BangGia;
use App\Models\DichVu;
use App\Models\LoaiDoGiat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PricingService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = BangGia::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('dichVu', fn($s) => $s->where('TenDichVu', 'LIKE', "%{$search}%"))
                  ->orWhereHas('loaiDoGiat', fn($g) => $g->where('TenLoaiDoGiat', 'LIKE', "%{$search}%"));
            });
        }

        if (!empty($filters['service_id'])) {
            $query->where('DichVuID', $filters['service_id']);
        }

        if (!empty($filters['garment_id'])) {
            $query->where('LoaiDoGiatID', $filters['garment_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $allowedSorts = ['BangGiaID', 'DonViTinhID', 'DonGia', 'NgayApDung', 'TrangThai', 'NgayTao'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'NgayApDung';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['dichVu', 'loaiDoGiat'])->orderBy($sortBy, $sortOrder)->paginate(10);
    }

    public function find(int $id): ?BangGia
    {
        return BangGia::withTrashed()->with(['dichVu', 'loaiDoGiat'])->find($id);
    }

    public function create(array $data): BangGia
    {
        return DB::transaction(function () use ($data) {
            return BangGia::create($data);
        });
    }

    public function update(BangGia $pricing, array $data): BangGia
    {
        $pricing->update($data);
        return $pricing->fresh();
    }

    public function delete(BangGia $pricing): bool
    {
        return $pricing->delete();
    }

    public function restore(int $id): ?BangGia
    {
        $pricing = BangGia::onlyTrashed()->find($id);
        if ($pricing) {
            $pricing->restore();
        }
        return $pricing;
    }

    public function getLatestPrice(int $serviceId, int $garmentId): ?float
    {
        return BangGia::getLatestPrice($serviceId, $garmentId);
    }
}