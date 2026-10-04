<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationLocalizationTest extends TestCase
{
    public function test_minimum_string_validation_is_displayed_in_vietnamese(): void
    {
        app()->setLocale('vi');

        $validator = Validator::make(
            ['new_password' => 'abc'],
            ['new_password' => 'min:8'],
            [],
            ['new_password' => 'mật khẩu mới'],
        );

        $this->assertSame('mật khẩu mới phải có ít nhất 8 ký tự.', $validator->errors()->first());
    }
}
