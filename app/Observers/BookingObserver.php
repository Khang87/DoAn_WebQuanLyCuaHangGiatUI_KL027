<?php

namespace App\Observers;

use App\Enums\BookingStatus;
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
        if ($booking->isDirty('TrangThai')
            && $booking->TrangThai === BookingStatus::Confirmed->value) {
            $originalStatus = $booking->getOriginal('TrangThai');

            if ($originalStatus === BookingStatus::Pending->value) {
                try {
                    $this->bookingService->confirmAndCreateOrder($booking);
                    Log::info('Auto-created order from booking', [
                        'booking_id' => $booking->BookingID,
                        'booking_code' => $booking->MaBooking,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to auto-create order from booking', [
                        'booking_id' => $booking->BookingID,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }
        }
    }
}
