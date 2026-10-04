<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LuuBangGiaRequest;
use Carbon\Carbon;
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

    public function test_pricing_application_date_cannot_be_before_today_and_has_a_vietnamese_message(): void
    {
        Carbon::setTestNow('2026-10-04 08:43:28');

        $request = LuuBangGiaRequest::create('/pricings', 'POST', [
            'NgayApDung_display' => '03-10-2026',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('A pricing application date before today should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Ngày áp dụng không được trước ngày hiện tại.',
                $exception->errors()['NgayApDung'][0],
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pricing_application_date_can_be_today(): void
    {
        Carbon::setTestNow('2026-10-04 08:43:28');

        $request = LuuBangGiaRequest::create('/pricings', 'POST', [
            'NgayApDung_display' => '04-10-2026',
            'NgayKetThuc_display' => '05-10-2026',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('Missing required pricing fields should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayNotHasKey('NgayApDung', $exception->errors());
        } finally {
            Carbon::setTestNow();
        }
    }
}
