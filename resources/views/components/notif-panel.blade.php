{{--
    Notifications dropdown, anchored under the topbar bell
    ([data-dropdown-trigger="notifPanel"] in header.blade.php).
    There is no notifications table/model in the system yet — this renders
    a small illustrative set built from recent, real activity (upcoming
    contract renewals) so the UI isn't showing fake data, while staying
    read-only. Wiring this to a persisted notifications feed is a separate
    feature, out of scope for this visual pass.
--}}
@php
    $proximasRenovaciones = \App\Models\Cliente::query()
        ->whereNotNull('fecha_renovacion_contrato')
        ->where('fecha_renovacion_contrato', '<=', now()->addDays(14))
        ->where('fecha_renovacion_contrato', '>=', now())
        ->orderBy('fecha_renovacion_contrato')
        ->limit(5)
        ->get(['id', 'empresa', 'nombre', 'fecha_renovacion_contrato']);
@endphp
<div class="dropdown-panel notif-panel" id="notifPanel" data-dropdown-panel hidden>
    <div class="notif-panel__header">
        <span class="notif-panel__title">Notificaciones</span>
    </div>
    <div class="notif-panel__list">
        @forelse ($proximasRenovaciones as $cliente)
            <a href="{{ route('admin.clientes.show', $cliente->id) }}" class="notif-panel__item">
                <span class="notif-panel__dot notif-panel__dot--warning"></span>
                <div>
                    <div class="notif-panel__text">Renovación próxima: {{ $cliente->empresa ?: $cliente->nombre }}</div>
                    <div class="notif-panel__meta">{{ $cliente->fecha_renovacion_contrato->diffForHumans() }}</div>
                </div>
            </a>
        @empty
            <div class="notif-panel__empty">Sin notificaciones por ahora.</div>
        @endforelse
    </div>
    <div class="notif-panel__footer">
        <a href="{{ route('admin.clientes.index') }}" class="btn btn--secondary btn--sm" style="width:100%; justify-content:center;">
            Ver clientes
        </a>
    </div>
</div>
