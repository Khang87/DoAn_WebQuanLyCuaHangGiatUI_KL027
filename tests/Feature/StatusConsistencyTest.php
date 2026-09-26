<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RecordStatus;
use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Garment;
use App\Models\GarmentCondition;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Bảo đảm 3 vị trí hiển thị trạng thái (dropdown edit / dropdown lọc / badge
 * trong bảng) luôn dùng chung một nguồn dữ liệu là enum.
 */
class StatusConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Trích các value của <option> bên trong thẻ select có `name` cho trước.
     */
    private function optionValues(string $html, string $selectName): array
    {
        if (! preg_match('/<select\b[^>]*\bname="' . preg_quote($selectName, '/') . '"[^>]*>(.*?)<\/select>/s', $html, $m)) {
            return [];
        }

        preg_match_all('/<option\s+value="([^"]*)"/', $m[1], $values);

        return $values[1];
    }

    /**
     * @return array<string, array{0: string, 1: string}> route name => [enum class, status field]
     */
    private function modules(): array
    {
        return [
            'orders.index' => [OrderStatus::class, 'orders'],
            'orders.create' => [OrderStatus::class, 'orders'],
            'orders.edit' => [OrderStatus::class, 'orders'],
            'invoices.index' => [InvoiceStatus::class, 'invoices'],
            'invoices.edit' => [InvoiceStatus::class, 'invoices'],
            'payments.index' => [PaymentStatus::class, 'payments'],
            'payments.create' => [PaymentStatus::class, 'payments'],
            'payments.edit' => [PaymentStatus::class, 'payments'],
            'bookings.index' => [BookingStatus::class, 'bookings'],
            'bookings.edit' => [BookingStatus::class, 'bookings'],
            'deliveries.index' => [DeliveryStatus::class, 'deliveries'],
            'deliveries.create' => [DeliveryStatus::class, 'deliveries'],
            'deliveries.edit' => [DeliveryStatus::class, 'deliveries'],
            'reviews.index' => [ReviewStatus::class, 'reviews'],
            'services.index' => [RecordStatus::class, 'services'],
            'services.edit' => [RecordStatus::class, 'services'],
            'garments.index' => [RecordStatus::class, 'garments'],
            'garments.edit' => [RecordStatus::class, 'garments'],
            'garment-conditions.index' => [RecordStatus::class, 'garment_conditions'],
            'garment-conditions.edit' => [RecordStatus::class, 'garment_conditions'],
            'service-categories.index' => [RecordStatus::class, 'service_categories'],
            'service-categories.edit' => [RecordStatus::class, 'service_categories'],
            'pricings.index' => [RecordStatus::class, 'pricings'],
            'pricings.edit' => [RecordStatus::class, 'pricings'],
            'promotions.index' => [RecordStatus::class, 'promotions'],
            'promotions.edit' => [RecordStatus::class, 'promotions'],
            'coupons.index' => [RecordStatus::class, 'coupons'],
            'coupons.edit' => [RecordStatus::class, 'coupons'],
        ];
    }

    private function seedModule(string $table): void
    {
        match ($table) {
            'orders' => Order::factory()->create(['status' => 'pending']),
            'invoices' => Invoice::factory()->create(['status' => 'unpaid']),
            // Khoản thu chưa ghi nhận + đơn chưa quyết toán, nên trang edit vẫn mở được.
            // (OrderFactory chọn status ngẫu nhiên, có thể ra 'completed' -> khoá)
            'payments' => Payment::factory()->create([
                'status' => 'pending',
                'order_id' => Order::factory()->create(['status' => 'processing'])->id,
            ]),
            'bookings' => Booking::factory()->create(['status' => 'pending']),
            'deliveries' => Delivery::factory()->create(['status' => 'pending']),
            'reviews' => Review::factory()->create(['status' => 'visible']),
            'services' => Service::factory()->create(['status' => 'active']),
            'garments' => Garment::factory()->create(['status' => 'active']),
            'garment_conditions' => GarmentCondition::factory()->create(['status' => 'active']),
            'service_categories' => ServiceCategory::factory()->create(['status' => 'active']),
            'pricings' => Pricing::factory()->create(['status' => 'active']),
            'promotions' => Promotion::factory()->create(['status' => 'active']),
            'coupons' => Coupon::factory()->create(['status' => 'active']),
        };
    }

    private function urlFor(string $routeName, string $table): string
    {
        $model = match ($table) {
            'orders' => Order::first(),
            'invoices' => Invoice::first(),
            'payments' => Payment::first(),
            'bookings' => Booking::first(),
            'deliveries' => Delivery::first(),
            'reviews' => Review::first(),
            'services' => Service::first(),
            'garments' => Garment::first(),
            'garment_conditions' => GarmentCondition::first(),
            'service_categories' => ServiceCategory::first(),
            'pricings' => Pricing::first(),
            'promotions' => Promotion::first(),
            'coupons' => Coupon::first(),
        };

        $parameters = str_contains($routeName, 'edit') ? [$model] : [];

        return route($routeName, $parameters);
    }

    /**
     * Mọi màn hình có dropdown trạng thái phải render được và chứa đúng tập
     * option của enum tương ứng.
     */
    public function test_every_status_dropdown_renders_the_enum_option_set(): void
    {
        $failures = [];

        foreach ($this->modules() as $routeName => [$enum, $table]) {
            $this->seedModule($table);
            $url = $this->urlFor($routeName, $table);

            $response = $this->actingAs($this->admin)->get($url);

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$routeName}: HTTP {$response->getStatusCode()}";
                continue;
            }

            $values = $this->optionValues($response->getContent(), 'status');
            $expected = array_keys($enum::options());
            $isFilter = str_contains($routeName, 'index');

            // Dropdown lọc có thêm một option "-- Tất cả trạng thái --" (value rỗng).
            $expected = $isFilter ? array_merge([''], $expected) : $expected;

            if ($values !== $expected) {
                $failures[] = sprintf(
                    '%s: expected [%s] got [%s]',
                    $routeName,
                    implode(',', $expected),
                    implode(',', $values)
                );
            }
        }

        $this->assertSame([], $failures, "Dropdown trạng thái lệch enum:\n" . implode("\n", $failures));
    }

    /**
     * Badge trong bảng phải dùng đúng nhãn tiếng Việt của enum.
     */
    public function test_table_badges_use_the_enum_label(): void
    {
        $this->seedModule('orders');
        $this->seedModule('invoices');
        $this->seedModule('payments');
        $this->seedModule('bookings');
        $this->seedModule('deliveries');
        $this->seedModule('services');
        $this->seedModule('garments');
        $this->seedModule('service_categories');
        $this->seedModule('coupons');
        $this->seedModule('garment_conditions');
        $this->seedModule('pricings');

        $expectations = [
            'orders.index' => OrderStatus::labelFor('pending'),
            'invoices.index' => InvoiceStatus::labelFor('unpaid'),
            'payments.index' => PaymentStatus::labelFor('paid'),
            'bookings.index' => BookingStatus::labelFor('pending'),
            'deliveries.index' => DeliveryStatus::labelFor('pending'),
            'services.index' => RecordStatus::labelFor('active'),
            'garments.index' => RecordStatus::labelFor('active'),
            'service-categories.index' => RecordStatus::labelFor('active'),
            'coupons.index' => RecordStatus::labelFor('active'),
            'garment-conditions.index' => RecordStatus::labelFor('active'),
            'pricings.index' => RecordStatus::labelFor('active'),
        ];

        $failures = [];

        foreach ($expectations as $routeName => $label) {
            $response = $this->actingAs($this->admin)->get(route($routeName));

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$routeName}: HTTP {$response->getStatusCode()}";
                continue;
            }

            if (! str_contains($response->getContent(), $label)) {
                $failures[] = "{$routeName}: thiếu nhãn badge \"{$label}\"";
            }
        }

        $this->assertSame([], $failures, "Badge trạng thái lệch enum:\n" . implode("\n", $failures));
    }

    /**
     * Dropdown lọc và dropdown trong form chỉnh sửa phải có đúng tập value
     * giống nhau cho từng module.
     */
    public function test_filter_and_edit_dropdowns_expose_the_same_values(): void
    {
        $this->seedModule('orders');
        $this->seedModule('invoices');
        $this->seedModule('payments');
        $this->seedModule('bookings');
        $this->seedModule('deliveries');
        $this->seedModule('services');
        $this->seedModule('garments');
        $this->seedModule('service_categories');
        $this->seedModule('pricings');
        $this->seedModule('promotions');
        $this->seedModule('coupons');
        $this->seedModule('garment_conditions');

        $tables = [
            'orders', 'invoices', 'payments', 'bookings', 'deliveries',
            'services', 'garments', 'service_categories', 'pricings',
            'promotions', 'coupons', 'garment_conditions',
        ];

        $failures = [];

        foreach ($tables as $table) {
            $indexRoute = $table . '.index';
            $editRoute = $table . '.edit';

            if (! Route::has($indexRoute) || ! Route::has($editRoute)) {
                continue;
            }

            $indexValues = array_values(array_filter(
                $this->optionValues(
                    $this->actingAs($this->admin)->get($this->urlFor($indexRoute, $table))->getContent(),
                    'status'
                ),
                fn ($v) => $v !== ''
            ));

            $editValues = array_values(array_filter(
                $this->optionValues(
                    $this->actingAs($this->admin)->get($this->urlFor($editRoute, $table))->getContent(),
                    'status'
                ),
                fn ($v) => $v !== ''
            ));

            if ($indexValues !== $editValues) {
                $failures[] = sprintf(
                    '%s: filter [%s] khác edit [%s]',
                    $table,
                    implode(',', $indexValues),
                    implode(',', $editValues)
                );
            }
        }

        $this->assertSame([], $failures, "Dropdown lọc lệch dropdown edit:\n" . implode("\n", $failures));
    }

    /**
     * Cấm các nhãn trạng thái lệch chuẩn đã nêu trong yêu cầu.
     */
    public function test_forbidden_status_wordings_are_absent(): void
    {
        $this->seedModule('garment_conditions');
        $this->seedModule('services');
        $this->seedModule('coupons');
        $this->seedModule('payments');
        $this->seedModule('service_categories');
        $this->seedModule('garments');
        $this->seedModule('promotions');
        $this->seedModule('pricings');

        $forbidden = ['>Khóa<', '>Tắt<', '>Hoạt động<', '>Một phần<', '>Đang áp dụng<', '>Tạm ngừng<'];

        $pages = [
            'garment-conditions.index', 'services.index', 'coupons.index',
            'payments.index', 'service-categories.index', 'garments.index',
            'promotions.index', 'pricings.index',
        ];

        foreach (['garments', 'coupons', 'services', 'service-categories', 'promotions', 'pricings'] as $table) {
            $pages[] = $table . '.show';
        }

        $found = [];

        foreach ($pages as $routeName) {
            $url = str_ends_with($routeName, '.show')
                ? route($routeName, $this->firstModel(str_replace('.show', '', $routeName)))
                : route($routeName);

            $html = $this->actingAs($this->admin)->get($url)->getContent();

            foreach ($forbidden as $needle) {
                if (str_contains($html, $needle)) {
                    $found[] = "{$routeName} chứa {$needle}";
                }
            }
        }

        $this->assertSame([], $found, "Còn nhãn trạng thái lệch chuẩn:\n" . implode("\n", $found));
    }

    private function firstModel(string $table)
    {
        return match ($table) {
            'garments' => Garment::first(),
            'coupons' => Coupon::first(),
            'services' => Service::first(),
            'service-categories' => ServiceCategory::first(),
            'promotions' => Promotion::first(),
            'pricings' => Pricing::first(),
        };
    }

    /**
     * Enum là nguồn duy nhất: đổi nhãn trong enum thì cả 3 vị trí cùng đổi.
     */
    public function test_enum_options_and_labels_never_diverge(): void
    {
        foreach ([
            RecordStatus::class,
            OrderStatus::class,
            InvoiceStatus::class,
            PaymentStatus::class,
            BookingStatus::class,
            DeliveryStatus::class,
            ReviewStatus::class,
        ] as $enum) {
            $options = $enum::options();

            $this->assertNotEmpty($options, "{$enum} phải có option");

            foreach ($enum::cases() as $case) {
                $this->assertArrayHasKey($case->value, $options, "{$enum}: thiếu option {$case->value}");
                $this->assertSame($case->label(), $options[$case->value], "{$enum}: nhãn của {$case->value} lệch");
                $this->assertNotSame('', $case->badgeClass(), "{$enum}: {$case->value} thiếu màu badge");
                $this->assertNotSame('', $case->icon(), "{$enum}: {$case->value} thiếu icon");
                $this->assertSame($case->label(), $enum::labelFor($case->value));
                $this->assertSame($case->badgeClass(), $enum::badgeClassFor($case->value));
                $this->assertSame($case->icon(), $enum::iconFor($case->value));
            }

            // options() phải phủ đúng tập giá trị, không thừa không thiếu.
            $this->assertSame($enum::values(), array_keys($options));
        }
    }

    /**
     * Module thanh toán và hóa đơn dùng chung nhãn cho cùng một ý nghĩa.
     * Lưu ý: Badge màu có thể khác nhau giữa các module.
     */
    public function test_payment_and_invoice_modules_share_the_same_wording(): void
    {
        $this->assertSame(InvoiceStatus::labelFor('unpaid'), PaymentStatus::labelFor('pending'));
        $this->assertSame(InvoiceStatus::labelFor('partial'), PaymentStatus::labelFor('partial'));
        $this->assertSame(InvoiceStatus::labelFor('paid'), PaymentStatus::labelFor('paid'));
    }

    /**
     * Enum phải chịu được giá trị lạ / legacy mà không ném lỗi.
     */
    public function test_status_resolvers_are_tolerant_to_unknown_values(): void
    {
        $this->assertSame('Đang hoạt động', RecordStatus::labelFor(1));
        $this->assertSame('Tạm ngưng', RecordStatus::labelFor('off'));
        $this->assertSame('Tạm ngưng', RecordStatus::labelFor(null));

        $this->assertSame('Đã thanh toán', InvoiceStatus::labelFor('completed'));
        // 'cancelled' không thuộc tập case của hóa đơn -> rơi về mặc định
        $this->assertSame('Chờ thanh toán', InvoiceStatus::labelFor('cancelled'));
        $this->assertSame('Chờ thanh toán', InvoiceStatus::labelFor(null));

        $this->assertSame('Chờ xác nhận', BookingStatus::labelFor('khong-co-that'));
        $this->assertSame('Chờ xác nhận', DeliveryStatus::labelFor(''));
        $this->assertSame('Hiển thị', ReviewStatus::labelFor(null));
        $this->assertSame('Chờ thanh toán', PaymentStatus::labelFor(null));
        $this->assertSame('Đã thanh toán', PaymentStatus::labelFor('paid'));
        $this->assertSame('Chờ xử lý', OrderStatus::labelFor(null));
    }

    /**
     * Mọi trang quản trị liên quan trạng thái phải trả về 200 (chặn lỗi
     * "Undefined variable" do thiếu biến $statuses / $orderStatusOptions).
     */
    public function test_status_pages_do_not_throw_undefined_variable_errors(): void
    {
        $this->seedModule('orders');
        $this->seedModule('invoices');
        $this->seedModule('payments');
        $this->seedModule('bookings');
        $this->seedModule('deliveries');
        $this->seedModule('services');
        $this->seedModule('garments');
        $this->seedModule('service_categories');
        $this->seedModule('pricings');
        $this->seedModule('promotions');
        $this->seedModule('coupons');
        $this->seedModule('garment_conditions');

        $failures = [];

        foreach ($this->modules() as $routeName => [$enum, $table]) {
            $url = $this->urlFor($routeName, $table);
            $response = $this->actingAs($this->admin)->get($url);

            if ($response->getStatusCode() !== 200) {
                $failures[] = "{$routeName} ({$url}): HTTP {$response->getStatusCode()}";
            }
        }

        $this->assertSame([], $failures, "Trang trạng thái bị lỗi:\n" . implode("\n", $failures));
    }

    public function test_customer_module_has_no_status_conflict(): void
    {
        Customer::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('customers.index'));

        $response->assertStatus(200);
    }
}
