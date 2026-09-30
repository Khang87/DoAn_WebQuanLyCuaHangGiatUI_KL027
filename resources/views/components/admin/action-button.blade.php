@props([
    'route',       // route name, e.g. 'orders.show'
    'params' => [], // route params array
    'permission',  // permission code required, e.g. 'orders.view'
    'class' => 'btn btn-order-action view',
    'title' => 'Xem',
    'icon' => 'bi bi-eye',
    'method' => 'GET', // GET, POST, DELETE
    'confirm' => null, // confirmation message for destructive actions
])

@can($permission)
    @if($method === 'GET')
        <a href="{{ route($route, $params) }}" class="{{ $class }}" title="{{ $title }}">
            <i class="{{ $icon }}"></i>
        </a>
    @else
        <form action="{{ route($route, $params) }}" method="POST" class="d-inline">
            @csrf
            @if($method === 'DELETE')
                @method('DELETE')
            @endif
            <button type="submit" class="{{ $class }}" title="{{ $title }}"
                @if($confirm) onclick="return confirm('{{ $confirm }}')" @endif>
                <i class="{{ $icon }}"></i>
            </button>
        </form>
    @endif
@endcan