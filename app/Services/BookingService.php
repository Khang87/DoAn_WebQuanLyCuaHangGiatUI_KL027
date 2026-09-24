<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookingService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Booking::query();

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['delivery_method'])) {
            $query->where('delivery_method', $filters['delivery_method']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhereHas('customer', function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
                $q->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        return $query->with('customer', 'service')->withTrashed()->latest()->paginate(10)->withQueryString();
    }

    public function find(int $id): ?Booking
    {
        return Booking::withTrashed()->find($id);
    }

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function update(Booking $booking, array $data): Booking
    {
        $booking->update($data);
        return $booking->fresh();
    }

    public function delete(Booking $booking): bool
    {
        return $booking->delete();
    }

    public function restore(int $id): ?Booking
    {
        $booking = Booking::onlyTrashed()->find($id);
        if ($booking) {
            $booking->restore();
        }
        return $booking;
    }
}
