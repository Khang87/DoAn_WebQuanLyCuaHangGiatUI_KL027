<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiDoGiat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CatalogAvailability
{
    /** Read exemptions from storage, never from loaded/modified relations or request IDs. */
    public static function persistedItems(Booking|DonHang|ChiTietDonHang|null $record): Collection
    {
        if (! $record?->exists) {
            return collect();
        }

        $id = $record->getRawOriginal($record->getKeyName());
        if ($record instanceof ChiTietDonHang) {
            return ChiTietDonHang::query()->whereKey($id)->get();
        }
        if ($record instanceof DonHang) {
            return ChiTietDonHang::query()->where('DonHangID', $id)->get();
        }

        $booking = Booking::query()->findOrFail($id);
        $items = $booking->chiTietBookings()->get();
        $declaredTuples = $items->map(fn ($item) => self::tuple($item->getAttributes()));

        // Unquantified requests must not duplicate exemptions already granted by detail lines.
        return $items->concat($booking->requestedServices()->get()->reject(
            fn ($item) => $declaredTuples->contains(self::tuple($item->getAttributes())),
        ));
    }

    /** Existing tuples may survive suspension; additional suspended lines may not. */
    public static function assertItemsAvailable(array $items, Booking|DonHang|ChiTietDonHang|null $record = null): void
    {
        if ($items === []) {
            return;
        }

        $services = DichVu::query()->availableForBooking()
            ->whereIn('DichVuID', array_map(fn ($item) => $item['DichVuID'] ?? $item['service_id'] ?? 0, $items))
            ->orderBy('DichVuID')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->sharedLock())
            ->pluck('DichVuID')->all();
        $garments = LoaiDoGiat::query()->availableForBooking()
            ->whereIn('LoaiDoGiatID', array_map(fn ($item) => $item['LoaiDoGiatID'] ?? $item['garment_id'] ?? 0, $items))
            ->orderBy('LoaiDoGiatID')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->sharedLock())
            ->pluck('LoaiDoGiatID')->all();
        $existing = self::persistedItems($record)->all();

        foreach ($items as $index => $item) {
            $tuple = self::tuple($item);
            $matched = false;
            foreach ($existing as $key => $line) {
                if ($tuple !== self::tuple($line->getAttributes())) {
                    continue;
                }
                // A supplied detail ID cannot borrow another line's exemption.
                foreach (['ChiTietDonHangID', 'ChiTietBookingID'] as $idColumn) {
                    if (! empty($item[$idColumn]) && (int) $item[$idColumn] !== (int) $line->getAttribute($idColumn)) {
                        continue 2;
                    }
                }
                unset($existing[$key]);
                $matched = true;
                break;
            }
            if ($matched) {
                continue;
            }

            foreach ([
                ['DichVuID', 'service_id', $services, 'Dịch vụ hoặc nhóm dịch vụ đã tạm ngưng, không thể chọn cho dòng mới.'],
                ['LoaiDoGiatID', 'garment_id', $garments, 'Loại đồ giặt hoặc danh mục đã tạm ngưng, không thể chọn cho dòng mới.'],
            ] as [$column, $alias, $available, $message]) {
                if (! in_array((int) ($item[$column] ?? $item[$alias] ?? 0), $available, true)) {
                    throw ValidationException::withMessages(["items.{$index}.{$column}" => $message]);
                }
            }
        }
    }

    private static function tuple(array $item): string
    {
        return implode(':', [
            (int) ($item['DichVuID'] ?? $item['service_id'] ?? 0),
            (int) ($item['LoaiDoGiatID'] ?? $item['garment_id'] ?? 0),
            (int) ($item['DonViTinhID'] ?? $item['unit_id'] ?? 0),
        ]);
    }
}
