<?php

namespace Tests\Feature;

use App\Support\ServiceIcon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ServiceIconPickerTest extends TestCase
{
    public function test_picker_shows_vietnamese_labels_and_the_selected_icon_preview(): void
    {
        View::share('errors', new ViewErrorBag);

        $html = Blade::render(
            '<x-admin.icon-picker name="Icon" label="Biểu tượng dịch vụ" :value="$selected" :default="$default" :options="$options" />',
            [
                'selected' => 'material:cleaning_services_outlined',
                'default' => 'material:cleaning_services_outlined',
                'options' => ServiceIcon::options(),
            ],
        );

        $this->assertStringContainsString('Sấy khô', $html);
        $this->assertStringContainsString('Tẩy trắng', $html);
        $this->assertStringNotContainsString('js-icon-search', $html);
        $this->assertStringNotContainsString('Tìm icon...', $html);
        $this->assertStringContainsString('js-icon-option', $html);
        $this->assertStringContainsString('value="material:cleaning_services_outlined"', $html);
        $this->assertStringContainsString('js-icon-current-preview', $html);
        $this->assertStringContainsString('js-icon-option-preview', $html);
        $this->assertStringContainsString('viewBox="0 0 512 512"', $html);
    }
}
