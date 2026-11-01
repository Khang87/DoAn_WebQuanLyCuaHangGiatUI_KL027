<?php

namespace Tests\Unit;

use App\Support\ServiceIcon;
use PHPUnit\Framework\TestCase;

class ServiceIconTest extends TestCase
{
    public function test_tẩy_service_uses_the_mobile_cleaning_icon_by_default(): void
    {
        $icon = ServiceIcon::resolve(null, 'Tẩy trắng siêu cấp');

        $this->assertSame('material:cleaning_services_outlined', $icon);
        $this->assertSame(
            [
                'color' => '#0D9488',
                'background' => 'rgba(13, 148, 136, 0.12)',
            ],
            ServiceIcon::colors($icon),
        );
    }

    public function test_service_names_use_the_same_icons_as_mobile(): void
    {
        $bleachingIcon = ServiceIcon::resolve(null, 'Tẩy trắng siêu cấp');
        $dryingIcon = ServiceIcon::resolve(null, 'Sấy khô');

        $this->assertSame('material:air_outlined', $dryingIcon);
        $this->assertNotSame($bleachingIcon, $dryingIcon);
        $this->assertSame(
            'material:dry_cleaning_outlined',
            ServiceIcon::resolve(null, 'Giặt khô nhanh'),
        );
        $this->assertSame(
            'material:iron_outlined',
            ServiceIcon::resolve(null, 'Ủi siêu tốc'),
        );
        $this->assertSame(
            'material:water_drop_outlined',
            ServiceIcon::resolve(null, 'Giặt thường'),
        );
    }

    public function test_category_icon_matches_mobile_category_first_resolution(): void
    {
        $this->assertSame(
            'material:air_outlined',
            ServiceIcon::resolve(null, 'Giặt siêu kỹ', 'Giặt sấy quần áo'),
        );
    }

    public function test_saved_service_icon_takes_precedence_over_the_default(): void
    {
        $this->assertSame(
            'material:air_outlined',
            ServiceIcon::resolve('material:air_outlined', 'Tẩy trắng siêu cấp'),
        );
    }

    public function test_unknown_service_icons_fall_back_to_the_default(): void
    {
        $this->assertSame(
            ServiceIcon::DEFAULT,
            ServiceIcon::resolve('material:unknown', 'Dịch vụ khác'),
        );
    }
}
