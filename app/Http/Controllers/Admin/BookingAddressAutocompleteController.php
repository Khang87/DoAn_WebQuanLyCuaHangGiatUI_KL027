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

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'SkyLaundryApp/1.0 (contact@skylaundry.com)',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $input,
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

            $suggestions[] = [
                'place_id' => $place['place_id'],
                'description' => (string) $place['display_name'],
                'lat' => (string) $place['lat'],
                'lon' => (string) $place['lon'],
            ];
        }

        return response()->json($suggestions);
    }
}
