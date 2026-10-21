<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LuuThanhToanRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocalizedDateInputTest extends TestCase
{
    public function test_canonical_datetime_local_value_is_accepted_without_losing_its_time(): void
    {
        $request = LuuThanhToanRequest::create('/payments', 'POST', [
            'paid_at' => '2026-10-22T18:43',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('The remaining payment fields should be required.');
        } catch (ValidationException $exception) {
            $this->assertArrayNotHasKey('paid_at', $exception->errors());
            $this->assertSame('2026-10-22T18:43', $request->input('paid_at'));
        }
    }

    public function test_localized_datetime_string_is_not_accepted_as_backend_input(): void
    {
        $request = LuuThanhToanRequest::create('/payments', 'POST', [
            'paid_at' => '22/10/2026 18:43',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));

        try {
            $request->validateResolved();
            $this->fail('Localized display values must be converted before submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('paid_at', $exception->errors());
        }
    }
}
