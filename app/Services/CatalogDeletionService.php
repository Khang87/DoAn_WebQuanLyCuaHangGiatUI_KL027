<?php

namespace App\Services;

use App\Models\DanhMucLoaiDoGiat;
use App\Models\DichVu;
use App\Models\LoaiDichVu;
use App\Models\LoaiDoGiat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogDeletionService
{
    public function confirmation(DichVu|LoaiDoGiat|LoaiDichVu|DanhMucLoaiDoGiat $record): string
    {
        $children = $this->children($record);
        $items = $children ?? new Collection([$record]);
        $label = match (true) {
            $record instanceof DichVu => 'Dịch vụ',
            $record instanceof LoaiDoGiat => 'Loại đồ giặt',
            default => 'Danh mục',
        };
        $childLabel = $record instanceof LoaiDichVu ? 'dịch vụ' : 'loại đồ giặt';

        if ($this->hasHistory($items)) {
            return $label." này đã từng phát sinh trong đơn hàng/Booking nên hệ thống sẽ chuyển sang trạng thái 'Tạm ngưng' để bảo toàn dữ liệu lịch sử."
                .($children !== null ? ' Các '.$childLabel.' thuộc danh mục cũng sẽ được tạm ngưng.' : '');
        }

        $relatedMessage = $children === null ? ' Bảng giá liên quan cũng sẽ bị xóa.' : '';
        if ($children?->isNotEmpty()) {
            $relatedMessage = ' Các '.$childLabel.' chưa sử dụng thuộc danh mục và bảng giá liên quan cũng sẽ bị xóa.';
        }

        return $label.' này chưa phát sinh trong đơn hàng/Booking và sẽ được xóa vĩnh viễn.'
            .$relatedMessage.' Hành động này không thể hoàn tác.';
    }

    public function delete(DichVu|LoaiDoGiat|LoaiDichVu|DanhMucLoaiDoGiat $record): bool
    {
        return DB::transaction(function () use ($record): bool {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $children = $this->children($locked, true);
            $items = $children ?? new Collection([$locked]);

            if ($this->hasHistory($items)) {
                foreach ($items as $item) {
                    $this->suspend($item);
                }
                if ($children !== null) {
                    $this->suspend($locked);
                }

                return false;
            }

            foreach ($items as $item) {
                $item->bangGias()->delete();
                $this->remove($item);
            }
            if ($children !== null) {
                $this->remove($locked);
            }

            return true;
        });
    }

    private function children(Model $record, bool $lock = false): ?Collection
    {
        $query = match (true) {
            $record instanceof LoaiDichVu => $record->dichVus(),
            $record instanceof DanhMucLoaiDoGiat => $record->loaiDoGiats(),
            default => null,
        };

        return $query?->orderBy($query->getRelated()->getKeyName())
            ->when($lock, fn ($query) => $query->lockForUpdate())->get();
    }

    private function hasHistory(Collection $items): bool
    {
        if ($items->isEmpty()) {
            return false;
        }

        $column = $items->first()->getKeyName();
        $ids = $items->modelKeys();
        foreach (['ChiTietDonHang', 'ChiTietBooking', 'BookingYeuCauDichVu'] as $table) {
            if (DB::table($table)->whereIn($column, $ids)->exists()) {
                return true;
            }
        }

        $inspection = DB::table('BookingKiemKe');
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Recursive matching also protects rejected inspections and their previous versions.
            $inspection->whereRaw('jsonb_path_exists("DuLieuThucTe", ?::jsonpath, ?::jsonb)', [
                '$.**."'.$column.'" ? (@ == $ids[*] || @ == $stringIds[*])',
                json_encode(['ids' => array_map('intval', $ids), 'stringIds' => array_map('strval', $ids)]),
            ]);
        } else {
            $inspection->whereExists(function ($query) use ($column, $ids): void {
                $query->selectRaw('1')->fromRaw('json_tree("BookingKiemKe"."DuLieuThucTe") as catalog_history')
                    ->where('catalog_history.key', $column)
                    ->whereIn('catalog_history.atom', [...$ids, ...array_map('strval', $ids)]);
            });
        }

        return $inspection->exists();
    }

    private function suspend(Model $record): void
    {
        if (! $record->update(['TrangThai' => 'Tạm ngưng'])) {
            throw new RuntimeException('Không thể tạm ngưng dữ liệu danh mục.');
        }
    }

    private function remove(Model $record): void
    {
        if (! $record->delete()) {
            throw new RuntimeException('Không thể xóa dữ liệu danh mục.');
        }
    }
}
