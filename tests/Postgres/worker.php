<?php

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\DeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/bootstrap.php';
require __DIR__.'/support.php';
$payload = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
pgActor(2);
DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[1]]);
try {
    $result = match ($payload['operation']) {
        'inspect' => app(BookingService::class)->inspectBookingAndCreateOrder(Booking::findOrFail($payload['booking_id']), 1, pgItems(), $payload['points'] ?? 0),
        'delivery' => app(DeliveryService::class)->create(['order_id' => $payload['order_id'], 'method' => 'giao_do', 'address' => 'Local return', 'employee_id' => 1]),
        default => throw new RuntimeException('Unknown worker operation.'),
    };
    echo json_encode(['status' => 'success', 'id' => $result->getKey()], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException $exception) {
    echo json_encode(['status' => 'rejected', 'fields' => array_keys($exception->errors())], JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(1);
}
