<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdminPaginationTest extends TestCase
{
    public function test_list_pagination_shows_one_summary_and_preserves_search_filter(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 28, 10, 1, [
            'path' => '/customers',
        ]);
        $paginator->appends(['search' => 'Lan']);

        $html = view('components.admin.pagination', [
            'paginator' => $paginator,
            'label' => 'khách hàng',
        ])->render();

        $this->assertSame(1, substr_count($html, 'Hiển thị'));
        $this->assertStringContainsString('Hiển thị 1 - 10 của 28 khách hàng', $html);
        $this->assertStringContainsString('search=Lan', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }
}
