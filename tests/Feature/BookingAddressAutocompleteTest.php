<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingAddressAutocompleteTest extends TestCase
{
    public function test_address_autocomplete_proxies_nominatim_results_with_required_user_agent(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'place_id' => 123,
                    'display_name' => '12 Nguyễn Huệ, Quận 1, Thành phố Hồ Chí Minh, Việt Nam',
                    'lat' => '10.7769',
                    'lon' => '106.7009',
                    'address' => ['road' => 'Nguyễn Huệ'],
                ],
            ]),
        ]);

        $this->withoutMiddleware()
            ->getJson('/api/osm/autocomplete?input='.urlencode('12 Nguyễn Huệ'))
            ->assertOk()
            ->assertExactJson([
                [
                    'place_id' => 123,
                    'description' => '12 Nguyễn Huệ, Quận 1, Thành phố Hồ Chí Minh, Việt Nam',
                    'lat' => '10.7769',
                    'lon' => '106.7009',
                ],
            ]);

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return parse_url($request->url(), PHP_URL_HOST) === 'nominatim.openstreetmap.org'
                && parse_url($request->url(), PHP_URL_PATH) === '/search'
                && $request->hasHeader('User-Agent', 'SkyLaundryApp/1.0 (contact@skylaundry.com)')
                && ($query['format'] ?? null) === 'json'
                && ($query['q'] ?? null) === '12 Nguyễn Huệ'
                && ($query['countrycodes'] ?? null) === 'vn'
                && ($query['addressdetails'] ?? null) === '1'
                && ($query['limit'] ?? null) === '5';
        });
    }

    public function test_address_autocomplete_rejects_short_queries_without_calling_nominatim(): void
    {
        Http::fake();

        $this->withoutMiddleware()
            ->getJson('/api/osm/autocomplete?input=ab')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('input');

        Http::assertNothingSent();
    }

    public function test_address_autocomplete_supports_hosts_that_strip_the_api_prefix(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([]),
        ]);

        $this->assertSame('/osm/autocomplete', route('bookings.address-autocomplete', [], false));

        $this->withoutMiddleware()
            ->getJson('/osm/autocomplete?input=Nguyễn%20Huệ')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_address_autocomplete_returns_gateway_error_when_nominatim_fails(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([], 503),
        ]);

        $this->withoutMiddleware()
            ->getJson('/api/osm/autocomplete?input=Nguyễn Huệ')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Không thể tải gợi ý địa chỉ lúc này. Vui lòng thử lại.');
    }
}
