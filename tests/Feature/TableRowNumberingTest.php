<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use DOMDocument;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TableRowNumberingTest extends TestCase
{
    private function table(string $view, array $data): DOMXPath
    {
        view()->share('errors', new ViewErrorBag);
        $html = view($view, $data)->render();
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($dom);
    }

    public static function pages(): array
    {
        return [[1, [1, 2, 3, 4, 5]], [2, [6, 7, 8, 9, 10]], [3, [11, 12]]];
    }

    #[DataProvider('pages')]
    public function test_paginated_rows_continue_across_pages_without_using_record_ids(int $page, array $numbers): void
    {
        $rows = collect($numbers)->map(fn (int $n) => (object) [
            'LoaiDoGiatID' => 100 + $n * 7, 'TenLoaiDoGiat' => 'Áo '.$n,
            'MoTa' => null, 'TrangThai' => 'Hoạt động',
        ]);
        $categories = new LengthAwarePaginator($rows, 12, 5, $page, ['path' => '/loaidogiat']);
        $xpath = $this->table('admin.loaidogiat.index', compact('categories'));
        $actual = [];
        foreach ($xpath->query('//table/tbody/tr/td[1]') as $cell) {
            $actual[] = (int) trim($cell->textContent);
        }
        $this->assertSame($numbers, $actual);
    }

    public function test_collection_rows_are_contiguous_even_when_keys_have_gaps(): void
    {
        $permissions = collect([4 => (object) ['MaQuyen' => 'ORDERS_VIEW', 'TenQuyen' => 'Xem', 'MoTa' => null, 'TrangThai' => 'Hoạt động'],
            19 => (object) ['MaQuyen' => 'ORDERS_EDIT', 'TenQuyen' => 'Sửa', 'MoTa' => null, 'TrangThai' => 'Hoạt động']]);
        $xpath = $this->table('admin.permissions.index', compact('permissions'));
        $this->assertSame('1', trim($xpath->query('//table/tbody/tr[1]/td[1]')->item(0)->textContent));
        $this->assertSame('2', trim($xpath->query('//table/tbody/tr[2]/td[1]')->item(0)->textContent));
        $this->assertSame(6, $xpath->query('//table/thead/tr/th')->length);
        $this->assertSame(0, $xpath->query('//table/thead/tr/th[normalize-space(.)="Nhóm được cấp"]')->length);
    }

    public function test_customer_list_uses_contiguous_row_numbers_instead_of_internal_ids(): void
    {
        $rows = collect([42, 900])->map(fn (int $id) => (new KhachHang)->forceFill([
            'KhachHangID' => $id, 'HoTen' => 'Khách '.$id,
        ])->setRelation('diemTichLuy', null));
        $customers = new LengthAwarePaginator($rows, 12, 10, 2, ['path' => '/customers']);
        $xpath = $this->table('admin.customers.index', compact('customers'));
        $this->assertSame('STT', trim($xpath->query('//table/thead/tr/th[1]')->item(0)->textContent));
        $this->assertSame(1, $xpath->query('//table/thead/tr/th[normalize-space(.)="STT"]')->length);
        $this->assertSame('11', trim($xpath->query('//table/tbody/tr[1]/td[1]')->item(0)->textContent));
        $this->assertSame('12', trim($xpath->query('//table/tbody/tr[2]/td[1]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//table/thead/tr/th[normalize-space(.)="ID khách hàng"]')->length);
    }

    public function test_status_select_trims_surrounding_whitespace_from_option_labels(): void
    {
        $html = view('components.admin.status-select', [
            'name' => 'TrangThai',
            'options' => ['Hoạt động' => " \tHoạt động \n"],
            'selected' => 'Hoạt động',
        ])->render();

        $this->assertStringContainsString('>Hoạt động</option>', $html);
        $this->assertStringNotContainsString('> Hoạt động ', $html);
    }

    public function test_empty_tables_span_all_visible_columns_without_a_fake_row_number(): void
    {
        foreach ([['admin.loaidogiat.index', 'categories', 5], ['admin.customers.index', 'customers', 7], ['admin.orders.index', 'orders', 11]] as [$view, $name, $columns]) {
            $xpath = $this->table($view, [$name => new LengthAwarePaginator([], 0, 10), 'statusFlow' => []]);
            $this->assertSame($columns, $xpath->query('//table/thead/tr/th')->length);
            $cell = $xpath->query('//table/tbody/tr/td')->item(0);
            $this->assertSame((string) $columns, $cell->getAttribute('colspan'));
            $this->assertNotSame('1', trim($cell->textContent));
        }
    }
}
