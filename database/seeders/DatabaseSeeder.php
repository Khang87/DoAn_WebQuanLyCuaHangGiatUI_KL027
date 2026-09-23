<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Garment;
use App\Models\GarmentCondition;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ServiceSeeder::class,
            GarmentSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
            PaymentSeeder::class,
            InvoiceSeeder::class,
            BookingSeeder::class,
            DeliverySeeder::class,
            PromotionSeeder::class,
            CouponSeeder::class,
            PricingSeeder::class,
            NotificationSeeder::class,
            GarmentConditionSeeder::class,
        ]);
    }
}
