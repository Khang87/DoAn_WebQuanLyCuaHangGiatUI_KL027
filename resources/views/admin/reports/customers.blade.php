@extends('layouts.app')

@section('title', 'Top khach hang - Sky Laundry')
@section('page-title', 'Top khach hang')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Top khach hang chi tieu cao nhat</h5>
        <a href="{{ route('reports.index') }}" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr><th>#</th><th>Ho ten</th><th>Email</th><th>SDT</th><th>Diem</th><th>So don</th><th>Tong chi tieu</th></tr>
                </thead>
                <tbody>
                    @forelse($topCustomers as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->customer?->name }}</strong>
                        </td>
                        <td>{{ $item->customer?->email ?: '-' }}</td>
                        <td>{{ $item->customer?->phone ?: '-' }}</td>
                        <td><span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 rounded-pill">{{ number_format($item->customer?->points ?? 0) }} <i class="fas fa-star"></i></span></td>
                        <td>{{ number_format($item->order_count) }}</td>
                        <td><strong class="text-primary">{{ number_format($item->total_spent) }} VND</strong></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Chua du lieu khach hang</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>