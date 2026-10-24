<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\KhachHang;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthorizationBoundaryTest extends TestCase
{
    public function test_sidebar_hides_unavailable_modules_and_empty_groups_before_rendering(): void
    {
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('isOwner')->andReturn(false);
        $staff->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => $permission === 'customers.view',
        );

        $this->actingAs($staff)
            ->view('layouts.app')
            ->assertSee('Khách hàng')
            ->assertDontSee('sidebar-menu-title">Giao nhận & Thanh toán', false)
            ->assertDontSee('sidebar-menu-title">Hệ thống', false)
            ->assertDontSee('sidebar-menu-title">Menu chính', false)
            ->assertDontSee('href="'.route('orders.index').'"', false);
    }

    public function test_customer_list_hides_edit_and_delete_for_view_only_role(): void
    {
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('isOwner')->andReturn(false);
        $staff->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => $permission === 'customers.view',
        );

        $customers = new LengthAwarePaginator([
            (new KhachHang)->forceFill([
                'KhachHangID' => 7,
                'HoTen' => 'Khách kiểm thử',
                'SoDienThoai' => null,
                'Email' => null,
                'DiaChi' => null,
                'NgayTao' => null,
            ])->setRelation('diemTichLuy', null)->setRelation('taiKhoan', null),
        ], 1, 10);

        $this->actingAs($staff)
            ->view('admin.customers.index', compact('customers'))
            ->assertSee(route('customers.show', 7), false)
            ->assertDontSee(route('customers.edit', 7), false)
            ->assertDontSee('id="deleteCustomerForm_7"', false);
    }

    public function test_customer_cannot_access_admin_routes(): void
    {
        $customer = Mockery::mock(User::class)->makePartial();
        $customer->shouldReceive('isActive')->andReturn(true);
        $customer->shouldReceive('isCustomer')->andReturn(true);

        $this->actingAs($customer)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_customer_cannot_access_order_api(): void
    {
        $customer = Mockery::mock(User::class)->makePartial();
        $customer->shouldReceive('isActive')->andReturn(true);
        $customer->shouldReceive('isCustomer')->andReturn(true);

        $this->actingAs($customer)
            ->getJson('/api/v1/orders')
            ->assertForbidden();
    }

    public function test_staff_with_view_permission_cannot_create_customers(): void
    {
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('isCustomer')->andReturn(false);
        $staff->shouldReceive('canPermission')
            ->with('customers.create')
            ->andReturn(false);

        $this->actingAs($staff)
            ->post('/customers', [])
            ->assertForbidden();
    }

    public function test_api_order_list_requires_view_permission(): void
    {
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('isCustomer')->andReturn(false);
        $staff->shouldReceive('canPermission')
            ->with('orders.view')
            ->andReturn(false);

        $this->actingAs($staff)
            ->getJson('/api/v1/orders')
            ->assertForbidden();
    }

    public function test_staff_without_order_delete_permission_is_denied(): void
    {
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('canPermission')
            ->with('orders.delete')
            ->andReturn(false);

        $request = Request::create('/orders/123', 'DELETE');
        $request->setUserResolver(fn () => $staff);

        try {
            (new EnsureUserHasPermission)->handle(
                $request,
                fn () => response('unexpectedly allowed'),
                'orders.delete',
            );
            $this->fail('A user without orders.delete must receive 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_resource_routes_use_action_specific_permissions(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains(
            'permission:customers.view',
            $routes->getByName('customers.index')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:customers.create',
            $routes->getByName('customers.store')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:orders.delete',
            $routes->getByName('orders.destroy')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:orders.view',
            $routes->getByName('api.v1.orders.index')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:orders.update_status',
            $routes->getByName('api.v1.orders.status')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:reports.view',
            $routes->getByName('reports.index')->getAction('middleware'),
        );
        $this->assertNull($routes->getByName('reports.export'));
        $this->assertNull($routes->getByName('invoices.export-excel'));
    }
}
