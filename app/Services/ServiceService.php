<?php

namespace App\Services;

use App\Models\DichVu;
use App\Models\LoaiDichVu;
use App\Models\LoaiDoGiat;
use App\Support\CatalogCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = DichVu::query()->with('loaiDichVu');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('TenDichVu', 'like', "%{$search}%")
                    ->orWhere('MoTa', 'like', "%{$search}%")
                    ->orWhereHas('loaiDichVu', function ($sub) use ($search) {
                        $sub->where('TenLoaiDichVu', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('LoaiDichVuID', $filters['category_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $sortMap = [
            'created_at_desc' => ['NgayTao', 'desc'],
            'created_at_asc' => ['NgayTao', 'asc'],
            'name_asc' => ['TenDichVu', 'asc'],
            'name_desc' => ['TenDichVu', 'desc'],
            'duration_asc' => ['ThoiGianDuKien', 'asc'],
            'duration_desc' => ['ThoiGianDuKien', 'desc'],
            'status_asc' => ['TrangThai', 'asc'],
            'status_desc' => ['TrangThai', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['NgayTao', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?DichVu
    {
        return DichVu::with([
            'loaiDichVu',
            'bangGias' => fn ($query) => $query
                ->with(['loaiDoGiat', 'donViTinh'])
                ->orderByDesc('NgayApDung')
                ->orderByDesc('BangGiaID'),
        ])->find($id);
    }

    public function create(array $data): DichVu
    {
        return DichVu::create($data);
    }

    public function update(DichVu $service, array $data): DichVu
    {
        $service->update($data);

        return $service->fresh(['loaiDichVu']);
    }

    public function delete(DichVu $service): bool
    {
        if (
            $service->bangGias()->exists()
            || $service->chiTietDonHangs()->exists()
            || $service->bookings()->exists()
        ) {
            $service->update(['TrangThai' => 'Tạm ngưng']);

            return false;
        }

        return $service->delete();
    }

    public function getCategories(): array
    {
        return CatalogCache::serviceCategories(fn (): array => LoaiDichVu::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('TenLoaiDichVu')
            ->pluck('TenLoaiDichVu', 'LoaiDichVuID')
            ->all());
    }

    public function getGarmentTypes(): array
    {
        return CatalogCache::garmentTypes(fn (): array => LoaiDoGiat::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('TenLoaiDoGiat')
            ->pluck('TenLoaiDoGiat', 'LoaiDoGiatID')
            ->all());
    }

    public function getCategoryOptions(): array
    {
        return $this->getCategories();
    }

    public function getGarmentTypeOptions(): array
    {
        return $this->getGarmentTypes();
    }
}
