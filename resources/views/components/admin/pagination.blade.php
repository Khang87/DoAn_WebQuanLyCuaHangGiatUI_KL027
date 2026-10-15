@props(['paginator', 'label' => 'kết quả'])

@if($paginator->hasPages())
    <div {{ $attributes->class(['mt-4', 'd-flex', 'flex-wrap', 'justify-content-between', 'align-items-center', 'gap-3']) }}>
        <small class="text-muted">
            Hiển thị {{ $paginator->firstItem() }} - {{ $paginator->lastItem() }} của {{ $paginator->total() }} {{ $label }}
        </small>
        {{ $paginator->withQueryString()->links('pagination::bootstrap-5', ['showSummary' => false]) }}
    </div>
@endif
