<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CustomerSeeder::class,
            ServiceCategorySeeder::class,
            CategorySeeder::class,
            ServiceSeeder::class,
            GarmentSeeder::class,
            GarmentConditionSeeder::class,
            PromotionSeeder::class,
            PricingSeeder::class,
            OrderSeeder::class,
            InvoiceSeeder::class,
            BookingSeeder::class,
            CouponSeeder::class,
            NotificationSeeder::class,
            ReviewSeeder::class,
        ]);
    }
}
