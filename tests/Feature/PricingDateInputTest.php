<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LuuBangGiaRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingDateInputTest extends TestCase
{
    public function test_pricing_dates_are_normalized_from_day_month_year_before_validation(): void
    {
        $request = LuuBangGiaRequest::create('/pricings', 'POST', [
            'NgayApDung_display' => '05-11-2026',
            'NgayKetThuc_display' => '31-12-2026',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('Missing required pricing fields should fail validation.');
        } catch (ValidationException) {
            $this->assertSame('2026-11-05', $request->input('NgayApDung'));
            $this->assertSame('2026-12-31', $request->input('NgayKetThuc'));
        }
    }

    public function test_invalid_day_month_year_input_is_not_normalized_into_a_valid_date(): void
    {
        $request = LuuBangGiaRequest::create('/pricings', 'POST', [
            'NgayApDung_display' => '31-02-2026',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('Invalid dates should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('31-02-2026', $request->input('NgayApDung'));
            $this->assertArrayHasKey('NgayApDung', $exception->errors());
        }
    }
}
