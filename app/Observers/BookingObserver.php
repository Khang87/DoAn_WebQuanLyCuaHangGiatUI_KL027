<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    public function __construct(
        private BookingService $bookingService,
    ) {}

    public function updated(Booking $booking): void
    {
        if ($booking->isDirty('status') && $booking->status === 'confirmed') {
            $originalStatus = $booking->getOriginal('status');

            if ($originalStatus === 'pending') {
                try {
                    $this->bookingService->confirmAndCreateOrder($booking);
                    Log::info('Auto-created order from booking', [
                        'booking_id' => $booking->id,
                        'booking_code' => 'BK' . str_pad((string) $booking->id, 4, '0', STR_PAD_LEFT),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to auto-create order from booking', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}