<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeliveryFeeService
{
    /**
     * Phí cho mỗi chặng tại nhà:
     * - 3 km đầu miễn phí.
     * - Mỗi km vượt quá 3 km tính 5.000đ.
     * - Làm tròn lên bội số 1.000đ.
     */
    public function feeForDistance(int $distanceMeters): int
    {
        $extraMeters = max(0, $distanceMeters - 3000);

        return (int) (ceil(($extraMeters * 5) / 1000) * 1000);
    }

    /**
     * Chuyển địa chỉ thành tọa độ bằng OpenStreetMap Nominatim.
     * Lưu tọa độ vào cache để hạn chế gọi dịch vụ nhiều lần.
     */
    private function coordinatesFromAddress(
        string $address,
        string $requestId,
        string $deliveryLeg,
        string $addressRole
    ): array {
        $address = trim($address);

        if ($address === '') {
            throw ValidationException::withMessages([
                'delivery' => 'Vui lòng nhập địa chỉ giao nhận hợp lệ.',
            ]);
        }

        $cacheKey = 'osm_geocode:'.sha1(mb_strtolower($address));
        $cacheMiss = false;
        $cacheReadCompleted = false;

        try {
            $coordinates = Cache::remember($cacheKey, now()->addDays(30), function () use (
                $address,
                $requestId,
                $deliveryLeg,
                $addressRole,
                &$cacheMiss
            ): array {
                $cacheMiss = true;
                $startedAt = hrtime(true);

                try {
                    $response = Http::timeout(15)
                        ->withHeaders([
                            'User-Agent' => config(
                                'services.osm.user_agent',
                                'LaundryManagementApp/1.0'
                            ),
                        ])
                        ->get('https://nominatim.openstreetmap.org/search', [
                            'q' => $address,
                            'format' => 'jsonv2',
                            'limit' => 1,
                            'countrycodes' => 'vn',
                        ]);
                } catch (Throwable $exception) {
                    $this->logHttpMeasurement(
                        'nominatim',
                        $requestId,
                        $deliveryLeg,
                        $startedAt,
                        null,
                        'connection_error',
                        $exception::class,
                        $addressRole,
                    );

                    throw $exception;
                }

                if (! $response->successful()) {
                    $this->logHttpMeasurement(
                        'nominatim',
                        $requestId,
                        $deliveryLeg,
                        $startedAt,
                        $response->status(),
                        'http_error',
                        'unsuccessful_http_status',
                        $addressRole,
                    );

                    throw ValidationException::withMessages([
                        'delivery' => 'Không thể tìm tọa độ địa chỉ lúc này. Vui lòng thử lại sau.',
                    ]);
                }

                $result = $response->json('0');

                if (
                    ! is_array($result)
                    || ! isset($result['lat'], $result['lon'])
                    || ! is_numeric($result['lat'])
                    || ! is_numeric($result['lon'])
                ) {
                    $this->logHttpMeasurement(
                        'nominatim',
                        $requestId,
                        $deliveryLeg,
                        $startedAt,
                        $response->status(),
                        'invalid_response',
                        'invalid_geocode_payload',
                        $addressRole,
                    );

                    throw ValidationException::withMessages([
                        'delivery' => 'Không tìm thấy địa chỉ trên bản đồ. Vui lòng nhập địa chỉ rõ hơn.',
                    ]);
                }

                $this->logHttpMeasurement(
                    'nominatim',
                    $requestId,
                    $deliveryLeg,
                    $startedAt,
                    $response->status(),
                    'success',
                    null,
                    $addressRole,
                );

                return [
                    'lat' => (float) $result['lat'],
                    'lon' => (float) $result['lon'],
                ];
            });
            $cacheReadCompleted = true;

            return $coordinates;
        } finally {
            $cacheOutcome = $cacheMiss ? 'miss' : ($cacheReadCompleted ? 'hit' : 'error');
            Log::log($cacheOutcome === 'error' ? 'warning' : 'info', 'delivery_fee.geocode_cache', [
                'request_id' => $requestId,
                'delivery_leg' => $deliveryLeg,
                'address_role' => $addressRole,
                'cache_outcome' => $cacheOutcome,
                'outcome' => $cacheReadCompleted ? 'success' : 'error',
            ]);
        }
    }

    /**
     * Lấy quãng đường lái xe từ cửa hàng đến địa chỉ khách
     * bằng OpenStreetMap Nominatim và OSRM.
     */
    public function distanceInMeters(
        string $destination,
        ?string $requestId = null,
        string $deliveryLeg = 'unspecified'
    ): int {
        $requestId ??= (string) Str::uuid();
        $storeAddress = config('services.osm.store_address');

        if (blank($storeAddress)) {
            throw ValidationException::withMessages([
                'delivery' => 'Website chưa cấu hình địa chỉ cửa hàng.',
            ]);
        }

        try {
            $store = $this->coordinatesFromAddress(
                $storeAddress,
                $requestId,
                $deliveryLeg,
                'store',
            );
            $customer = $this->coordinatesFromAddress(
                $destination,
                $requestId,
                $deliveryLeg,
                'destination',
            );

            // OSRM yêu cầu thứ tự tọa độ: kinh độ, vĩ độ.
            $coordinates = $store['lon'].','.$store['lat']
                .';'.$customer['lon'].','.$customer['lat'];

            $startedAt = hrtime(true);

            try {
                $response = Http::timeout(20)
                    ->get(
                        'https://router.project-osrm.org/route/v1/driving/'
                            .$coordinates,
                        [
                            'overview' => 'false',
                            'alternatives' => 'false',
                            'steps' => 'false',
                        ]
                    );
            } catch (Throwable $exception) {
                $this->logHttpMeasurement(
                    'osrm',
                    $requestId,
                    $deliveryLeg,
                    $startedAt,
                    null,
                    'connection_error',
                    $exception::class,
                );

                throw $exception;
            }

            if (! $response->successful()) {
                $this->logHttpMeasurement(
                    'osrm',
                    $requestId,
                    $deliveryLeg,
                    $startedAt,
                    $response->status(),
                    'http_error',
                    'unsuccessful_http_status',
                );

                throw ValidationException::withMessages([
                    'delivery' => 'Không tính được tuyến đường. Vui lòng thử lại sau.',
                ]);
            }

            $routeCode = $response->json('code');
            $meters = $response->json('routes.0.distance');

            if (
                $routeCode !== 'Ok'
                || ! is_numeric($meters)
                || (float) $meters < 0
            ) {
                $this->logHttpMeasurement(
                    'osrm',
                    $requestId,
                    $deliveryLeg,
                    $startedAt,
                    $response->status(),
                    'invalid_response',
                    'invalid_route_payload',
                );

                throw ValidationException::withMessages([
                    'delivery' => 'Không tìm thấy tuyến đường lái xe hợp lệ đến địa chỉ này.',
                ]);
            }

            $this->logHttpMeasurement(
                'osrm',
                $requestId,
                $deliveryLeg,
                $startedAt,
                $response->status(),
                'success',
                null,
            );

            return (int) round((float) $meters);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {

            throw ValidationException::withMessages([
                'delivery' => 'Dịch vụ tính quãng đường đang gặp lỗi. Vui lòng thử lại.',
            ]);
        }
    }

    /**
     * Tính riêng phí lấy đồ và phí giao đồ sạch.
     */
    public function quote(
        string $receiveMethod,
        ?string $pickupAddress,
        string $returnMethod,
        ?string $returnAddress,
        ?string $requestId = null
    ): array {
        $requestId ??= (string) Str::uuid();
        $startedAt = hrtime(true);
        $outcome = 'success';
        $errorType = null;
        $deliveryLegs = [];
        $pickupDistance = 0;
        $pickupFee = 0;
        $returnDistance = 0;
        $returnFee = 0;

        try {
            if ($receiveMethod === 'Tại nhà') {
                $deliveryLegs[] = 'pickup';
                $pickupDistance = $this->distanceInMeters(
                    (string) $pickupAddress,
                    $requestId,
                    'pickup',
                );
                $pickupFee = $this->feeForDistance($pickupDistance);
            }

            if ($returnMethod === 'Tại nhà') {
                // Vẫn tính riêng phí từng chặng theo quy tắc hiện tại.
                $deliveryLegs[] = 'return';
                $returnDistance = $this->distanceInMeters(
                    (string) $returnAddress,
                    $requestId,
                    'return',
                );
                $returnFee = $this->feeForDistance($returnDistance);
            }

            return [
                'pickup_distance_meters' => $pickupDistance,
                'pickup_fee' => $pickupFee,
                'return_distance_meters' => $returnDistance,
                'return_fee' => $returnFee,
                'total_fee' => $pickupFee + $returnFee,
            ];
        } catch (Throwable $exception) {
            $outcome = 'error';
            $errorType = $exception::class;

            throw $exception;
        } finally {
            Log::log($outcome === 'success' ? 'info' : 'warning', 'delivery_fee.quote', [
                'request_id' => $requestId,
                'delivery_leg' => $deliveryLegs === [] ? 'none' : implode('+', $deliveryLegs),
                'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
                'outcome' => $outcome,
                'error_type' => $errorType,
            ]);
        }
    }

    private function logHttpMeasurement(
        string $provider,
        string $requestId,
        string $deliveryLeg,
        int $startedAt,
        ?int $httpStatus,
        string $outcome,
        ?string $errorType,
        ?string $addressRole = null
    ): void {
        Log::log($outcome === 'success' ? 'info' : 'warning', 'delivery_fee.http_request', array_filter([
            'request_id' => $requestId,
            'provider' => $provider,
            'delivery_leg' => $deliveryLeg,
            'address_role' => $addressRole,
            'http_status' => $httpStatus,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
            'outcome' => $outcome,
            'error_type' => $errorType,
        ], static fn (mixed $value): bool => $value !== null));
    }
}
