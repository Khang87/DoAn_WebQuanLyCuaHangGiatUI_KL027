<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phân quyền động (RBAC) + đặc quyền Chủ cửa hàng.
 *
 * Quy tắc nghiệp vụ: Nhân viên & Quản lý KHÔNG BAO GIỜ được cấp
 * orders.edit_completed / orders.delete_completed / invoices.edit_paid /
 * invoices.delete_paid / orders.refund. Chỉ Chủ cửa hàng mở khoá được các
 * thao tác trên bản ghi đã quyết toán.
 */
class DynamicRbacTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function paidOrder(): Order
    {
        $order = Order::create([
            'code' => 'DH-RBAC-' . random_int(1000, 9999),
            'customer_id' => $this->customer->id,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'processing',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        return $order->refresh()->load('payments', 'invoice');
    }

    // ---------- Ma trận quyền ----------

    public function test_seeder_creates_four_roles_and_permissions(): void
    {
        $this->assertSame(4, Role::count());
        $this->assertSame(
            ['owner', 'manager', 'staff', 'customer'],
            Role::orderBy('id')->pluck('slug')->all(),
        );

        $this->assertGreaterThan(0, Permission::count());
    }

    public function test_owner_only_permissions_are_never_granted_to_other_roles(): void
    {
        foreach (['manager', 'staff', 'customer'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();

            $leaked = $role->permissions
                ->whereIn('code', Role::OWNER_ONLY_PERMISSIONS)
                ->pluck('code')
                ->all();

            $this->assertSame([], $leaked, "Vai trò {$slug} không được giữ quyền đặc biệt.");
        }
    }

    public function test_owner_holds_financial_privileges(): void
    {
        $owner = Role::where('slug', 'owner')->firstOrFail();

        foreach (['orders.edit_completed', 'orders.refund', 'invoices.edit_paid'] as $code) {
            $this->assertTrue(
                $owner->permissions->contains('code', $code),
                "Chủ cửa hàng phải giữ quyền {$code}.",
            );
        }
    }

    public function test_staff_cannot_ever_gain_owner_only_permission(): void
    {
        $staff = $this->user('staff');
        $role = Role::where('slug', 'staff')->firstOrFail();

        // Cố gắng gán quyền đặc biệt cho Nhân viên.
        $permission = Permission::where('code', 'orders.refund')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->assertFalse(
            $staff->fresh()->canPermission('orders.refund'),
            'Nhân viên không được giữ quyền hoàn tiền dù DB có gán.',
        );
    }

    // ---------- Ma trận trên giao diện ----------

    public function test_owner_can_open_permission_matrix(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('roles.index'))
            ->assertStatus(200)
            ->assertSee('Quản lý phân quyền')
            ->assertSee('permissions[manager][]', false)
            ->assertDontSee('permissions[owner][]');
    }

    public function test_manager_cannot_open_permission_matrix(): void
    {
        $this->actingAs($this->user('manager'))
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_owner_can_save_matrix(): void
    {
        $manager = Role::where('slug', 'manager')->firstOrFail();
        $manager->permissions()->detach();

        $this->actingAs($this->user('admin'))
            ->put(route('roles.update'), [
                'permissions' => [
                    'manager' => ['orders.view', 'orders.create'],
                    'staff' => ['orders.view'],
                ],
            ])
            ->assertRedirect(route('roles.index'));

        $manager->refresh()->load('permissions');

        $this->assertCount(2, $manager->permissions);
        $this->assertTrue($manager->permissions->contains('code', 'orders.view'));
    }

    public function test_saving_matrix_rejects_owner_only_permission_for_manager(): void
    {
        $manager = Role::where('slug', 'manager')->firstOrFail();
        $manager->permissions()->detach();

        $this->actingAs($this->user('admin'))
            ->put(route('roles.update'), [
                'permissions' => [
                    'manager' => ['orders.view', 'orders.refund'],
                ],
            ])
            ->assertRedirect(route('roles.index'));

        $manager->refresh()->load('permissions');

        $this->assertFalse(
            $manager->permissions->contains('code', 'orders.refund'),
            'Payload gửi lên không được cấp quyền hoàn tiền cho Quản lý.',
        );
    }

    // ---------- Đặc quyền trên bản ghi đã quyết toán ----------

    public function test_owner_can_edit_paid_order(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->user('admin'))
            ->get(route('orders.edit', $order->id))
            ->assertStatus(200);
    }

    public function test_manager_cannot_edit_paid_order(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->user('manager'))
            ->get(route('orders.edit', $order->id))
            ->assertForbidden();
    }

    public function test_staff_cannot_edit_paid_order(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->user('staff'))
            ->put(route('orders.update', $order->id), [
                'code' => $order->code,
                'customer_id' => $this->customer->id,
                'status' => 'cancelled',
                'total_amount' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_amount' => 100000,
        ]);
    }

    public function test_manager_cannot_delete_paid_order(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->user('manager'))
            ->delete(route('orders.destroy', $order->id))
            ->assertForbidden();

        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_manager_cannot_open_paid_invoice_edit_page(): void
    {
        $order = $this->paidOrder();

        $invoice = Invoice::create([
            'code' => 'HD-RBAC-' . random_int(1000, 9999),
            'order_id' => $order->id,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'grand_total' => 100000,
            'total' => 100000,
            'status' => 'paid',
        ]);

        $this->actingAs($this->user('manager'))
            ->get(route('invoices.edit', $invoice->id))
            ->assertForbidden();
    }

    public function test_manager_cannot_delete_paid_invoice(): void
    {
        $order = $this->paidOrder();

        $invoice = Invoice::create([
            'code' => 'HD-RBAC-DEL-' . random_int(1000, 9999),
            'order_id' => $order->id,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'grand_total' => 100000,
            'total' => 100000,
            'status' => 'paid',
        ]);

        $this->actingAs($this->user('manager'))
            ->delete(route('invoices.destroy', $invoice->id))
            ->assertForbidden();

        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    // ---------- Ánh xạ vai trò legacy ----------

    public function test_employee_legacy_role_maps_to_staff_permissions(): void
    {
        // 'employee' là bí danh lịch sử, không có hàng trong bảng roles.
        // Nếu không ánh xạ thì tài khoản rơi về customer và mất sạch quyền.
        $employee = $this->user('employee');

        $this->assertSame('staff', $employee->roleSlug());
        $this->assertSame(Role::where('slug', 'staff')->value('id'), $employee->role_id);
        $this->assertTrue($employee->canPermission('orders.view'));
        $this->assertFalse($employee->canPermission('orders.refund'));
    }

    public function test_has_role_accepts_legacy_names(): void
    {
        $owner = $this->user('admin');
        $this->assertTrue($owner->hasRole('admin'));
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertTrue($owner->isOwner());

        $manager = $this->user('manager');
        $this->assertTrue($manager->hasRole('manager'));
        $this->assertFalse($manager->hasRole('admin'));
        $this->assertFalse($manager->isOwner());
    }

    public function test_paid_order_buttons_hidden_for_manager_but_shown_for_owner(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->user('manager'))
            ->get(route('orders.index'))
            ->assertStatus(200)
            ->assertDontSee('deleteOrderForm_' . $order->id);

        $this->actingAs($this->user('admin'))
            ->get(route('orders.index'))
            ->assertStatus(200)
            ->assertSee('deleteOrderForm_' . $order->id);
    }

    // ---------- Gán vai trò cho tài khoản ----------

    public function test_new_account_gets_matching_dynamic_role(): void
    {
        $user = $this->user('staff');

        $this->assertSame('staff', $user->roleRole?->slug);
        $this->assertTrue($user->canPermission('orders.view'));
    }

    public function test_staff_cannot_manage_roles(): void
    {
        // Nhân viên bị chặn ngay ở middleware vai trò quản lý.
        $this->actingAs($this->user('staff'))
            ->get(route('roles.index'))
            ->assertRedirect(route('staff.dashboard'))
            ->assertSessionHas('error');
    }
}
