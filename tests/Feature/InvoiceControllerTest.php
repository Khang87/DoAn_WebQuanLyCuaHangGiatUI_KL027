<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_invoice_export_route(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('invoices.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_staff_cannot_access_invoice_export_route(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->actingAs($user)->get(route('invoices.export'));

        // Báo cáo tài chính chỉ dành cho quản lý, nhân viên bị chặn.
        $response->assertRedirect(route('staff.dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_unauthenticated_users_are_redirected_from_invoice_export(): void
    {
        $response = $this->get(route('invoices.export'));

        $response->assertRedirect(route('login'));
    }

    public function test_invoice_export_includes_query_parameters(): void
    {
        $user = User::factory()->admin()->create();

        Order::factory()->create(['code' => 'DH-INV-001']);
        $invoice = Invoice::factory()->create(['code' => 'HD001', 'status' => 'unpaid']);

        $response = $this->actingAs($user)->get(route('invoices.export', ['status' => 'unpaid']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}

