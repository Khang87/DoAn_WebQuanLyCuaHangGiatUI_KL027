<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingAddressAutocompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'input' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        $input = trim($validated['input']);
        $context = trim((string) config('services.osm.autocomplete_context', ''));
        $query = preg_match('/^\d+$/', $input) === 1 && $context !== ''
            ? $input.' '.$context
            : $input;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'SkyLaundryApp/1.0 (contact@skylaundry.com)',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $query,
                    'countrycodes' => 'vn',
                    'addressdetails' => 1,
                    'limit' => 5,
                ]);
            $response->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('OpenStreetMap address autocomplete request failed.', [
                'exception' => $exception::class,
                'status' => $exception instanceof RequestException
                    ? $exception->response->status()
                    : null,
            ]);

            return response()->json([
                'message' => 'Không thể tải gợi ý địa chỉ lúc này. Vui lòng thử lại.',
            ], 502);
        }

        $places = $response->json();
        if (! is_array($places) || ! array_is_list($places)) {
            Log::warning('OpenStreetMap address autocomplete returned an invalid response.');

            return response()->json([
                'message' => 'Dịch vụ gợi ý địa chỉ trả về dữ liệu không hợp lệ.',
            ], 502);
        }

        $suggestions = [];
        foreach ($places as $place) {
            if (
                ! is_array($place)
                || ! isset($place['place_id'], $place['display_name'], $place['lat'], $place['lon'])
            ) {
                Log::warning('OpenStreetMap address autocomplete returned an incomplete place.');

                return response()->json([
                    'message' => 'Dịch vụ gợi ý địa chỉ trả về dữ liệu không hợp lệ.',
                ], 502);
            }

            $address = is_array($place['address'] ?? null) ? $place['address'] : [];
            $street = implode(' ', array_filter([
                $address['house_number'] ?? null,
                $address['road'] ?? null,
            ], fn ($part): bool => is_string($part) && trim($part) !== ''));
            $description = (string) $place['display_name'];
            if ($street !== '' && is_string($address['house_number'] ?? null) && trim($address['house_number']) !== '') {
                $parts = [
                    $street,
                    $address['neighbourhood'] ?? $address['quarter'] ?? null,
                    $address['suburb'] ?? $address['city_district'] ?? null,
                    $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
                    $address['postcode'] ?? null,
                    $address['country'] ?? null,
                ];
                $description = implode(', ', array_unique(array_filter(
                    $parts,
                    fn ($part): bool => is_string($part) && trim($part) !== '',
                )));
            }

            $suggestions[] = [
                'place_id' => $place['place_id'],
                'description' => $description,
                'lat' => (string) $place['lat'],
                'lon' => (string) $place['lon'],
            ];
        }

        return response()->json($suggestions);
    }
}
