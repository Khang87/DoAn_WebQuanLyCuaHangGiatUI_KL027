<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th scope="col">STT</th>
                        <th>Dịch vụ</th>
                        <th>Loại đồ giặt</th>
                        <th>Đơn vị</th>
                        <th>Đơn giá</th>
                        <th>Ngày áp dụng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pricings as $pricing)
                    <tr>
                        <td>{{ $pricings->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $pricing->dichVu?->TenDichVu ?: $pricing->loaiDoGiat?->TenLoaiDoGiat ?: '—' }}</strong></td>
                        <td>{{ $pricing->loaiDoGiat?->TenLoaiDoGiat ?: '—' }}</td>
                        <td>{{ $pricing->donViTinh?->KyHieu ?? $pricing->donViTinh?->TenDonViTinh ?: 'kg' }}</td>
                        <td class="fw-semibold text-dark">{{ number_format($pricing->DonGia) }} VNĐ</td>
                        <td>{{ $pricing->NgayApDung?->format('d/m/Y') ?: '—' }}</td>
                        <td>
                            <x-admin.status-badge :status="$pricing->TrangThai" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                @can('pricings.view')
                                    <a href="{{ route('pricings.show', $pricing) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('pricings.edit')
                                    <a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('pricings.delete')
                                    <form action="{{ route('pricings.destroy', $pricing) }}" method="POST" class="d-inline" id="deletePricingForm_{{ $pricing->BangGiaID }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Chưa có bảng giá nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$pricings" label="bảng giá" />
