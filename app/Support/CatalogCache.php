<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

class CatalogCache
{
    private const SERVICE_CATEGORIES_KEY = 'catalog.active_service_categories';

    private const GARMENT_TYPES_KEY = 'catalog.active_garment_types';

    private const UNITS_KEY = 'catalog.active_units';

    public static function serviceCategories(Closure $resolver): array
    {
        return Cache::remember(self::SERVICE_CATEGORIES_KEY, now()->addMinutes(10), $resolver);
    }

    public static function garmentTypes(Closure $resolver): array
    {
        return Cache::remember(self::GARMENT_TYPES_KEY, now()->addMinutes(10), $resolver);
    }

    public static function units(Closure $resolver): array
    {
        return Cache::remember(self::UNITS_KEY, now()->addMinutes(10), $resolver);
    }

    public static function forgetServiceCategories(): void
    {
        Cache::forget(self::SERVICE_CATEGORIES_KEY);
    }

    public static function forgetGarmentTypes(): void
    {
        Cache::forget(self::GARMENT_TYPES_KEY);
    }

    public static function forgetUnits(): void
    {
        Cache::forget(self::UNITS_KEY);
    }
}
