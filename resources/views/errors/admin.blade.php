{{--
    Error 404/403 dentro del panel (/admin/*): usa el layout del panel, no el del sitio público.
    Lo elige App\Exceptions\Handler; el contexto (mensaje, cliente activo) sale de App\Support\ErrorPanel.
--}}
@extends('layouts.admin')

@section('content')
    <div class="card empty-state" data-error-panel data-error-status="{{ $error['status'] }}" @if ($error['otro_cliente']) data-error-otro-cliente @endif>
        <div class="empty-state__icon"><i class="fa-solid {{ $error['status'] === 403 ? 'fa-lock' : 'fa-triangle-exclamation' }}"></i></div>
        <h1 class="page-header__title">{{ $error['status'] }} · {{ $error['titulo'] }}</h1>
        <p class="empty-state__text">{{ $error['mensaje'] }}</p>

        <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-top: var(--space-4);">
            @if ($error['cliente_activo'])
                <form method="POST" action="{{ route('admin.cliente-activo') }}">
                    @csrf
                    <input type="hidden" name="cliente_id" value="">
                    <button type="submit" class="btn {{ $error['otro_cliente'] ? 'btn--primary' : 'btn--secondary' }}">Ver todos los clientes</button>
                </form>
            @endif
            <a href="{{ url('/admin') }}" class="btn btn--secondary">Ir al inicio del panel</a>
            <button type="button" class="btn btn--secondary" onclick="history.back()">Volver</button>
        </div>
    </div>
@endsection
