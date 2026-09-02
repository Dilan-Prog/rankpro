@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/integraciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Integraciones</h1>
            <p class="page-header__subtitle">{{ $clientesConectados }} de {{ $clientesTotal }} clientes con tracking activo</p>
        </div>
    </div>

    {{-- ---------- KPIs (real, computed from tracking-pixel data) ---------- --}}
    <div class="kpi-grid">
        <x-stat-card label="Clientes Conectados" value="{{ $clientesConectados }}/{{ $clientesTotal }}" icon="fa-key" color="emerald" />
        <x-stat-card label="Conversiones Pendientes" value="{{ $pendientesTotal }}" sub="por exportar a Google Ads" icon="fa-file-export" color="amber" />
        <x-stat-card label="Última Señal" value="{{ $ultimaSenalGlobalLabel }}" icon="fa-signal" color="primary" />
    </div>

    {{-- ---------- Real section: tracking por cliente — links out to the already-built
         admin.clientes.integraciones page (token, script, clics, conversiones). ---------- --}}
    <div class="integraciones-section-head">
        <h2 class="integraciones-section-title">Tracking por cliente</h2>
        @if ($clientes->count() > 6)
            <input type="search" class="input input--search" id="integracionClienteSearch" placeholder="Buscar cliente...">
        @endif
    </div>

    @if ($clientes->isEmpty())
        <div class="card empty-state">
            <div class="empty-state__icon"><i class="fa-solid fa-user-slash"></i></div>
            <p class="empty-state__text">Aún no hay clientes registrados.</p>
        </div>
    @else
        <div class="empty-state" data-integracion-cliente-empty hidden>
            <div class="empty-state__icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <p class="empty-state__text">No se encontraron clientes.</p>
        </div>

        <div class="integracion-cliente-grid" data-integracion-cliente-grid>
            @foreach ($clientes as $c)
                <a href="{{ $c['show_url'] }}" class="integracion-cliente-card" data-integracion-cliente-card data-search="{{ mb_strtolower($c['cliente']) }}">
                    <div class="integracion-cliente-card__header">
                        <span class="integracion-cliente-card__avatar">{{ \App\Support\Labels::initials($c['cliente']) }}</span>
                        <span class="integracion-cliente-card__name">{{ $c['cliente'] }}</span>
                    </div>
                    <div class="integracion-cliente-card__status">
                        <span class="integracion-cliente-card__dot integracion-cliente-card__dot--{{ $c['conectado'] ? 'on' : 'off' }}"></span>
                        <span>{{ $c['conectado'] ? 'Conectado' : 'Sin conectar' }}</span>
                    </div>
                    <div class="integracion-cliente-card__body">
                        @if ($c['pendientes_count'] > 0)
                            <div class="integracion-cliente-card__stat">
                                <span class="integracion-cliente-card__stat-label">Pendientes</span>
                                <span class="integracion-cliente-card__stat-value">{{ $c['pendientes_count'] }} conversión{{ $c['pendientes_count'] === 1 ? '' : 'es' }}</span>
                            </div>
                        @endif
                        <div class="integracion-cliente-card__stat">
                            <span class="integracion-cliente-card__stat-label">Última señal</span>
                            <span class="integracion-cliente-card__stat-value">{{ $c['ultima_senal_label'] }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ---------- Próximamente: 8 fake third-party SaaS connectors — genuinely
         decorative, no real backend behind them, kept honestly locked. ---------- --}}
    <div class="integraciones-section-head integraciones-section-head--proximamente">
        <h2 class="integraciones-section-title">Próximamente</h2>
    </div>

    <x-alert variant="banner" icon="fa-bolt" color="#F59E0B">
        Este módulo está en desarrollo activo. Las integraciones estarán disponibles próximamente. Puedes solicitar una integración prioritaria a tu account manager.
    </x-alert>

    <div class="integraciones-grid">
        @foreach ($integraciones as $integracion)
            <div class="integraciones-card-wrap">
                <div class="card card--padded integraciones-card">
                    <div class="integraciones-card__head">
                        <span class="integraciones-card__icon"><i class="fa-{{ $integracion['brand'] ? 'brands' : 'solid' }} {{ $integracion['icon'] }}"></i></span>
                        <div>
                            <div style="font-weight:600; font-size:var(--text-sm);">{{ $integracion['name'] }}</div>
                            <div style="font-size:var(--text-xs); color:var(--color-muted-foreground); margin-top:2px;">{{ $integracion['desc'] }}</div>
                        </div>
                    </div>
                    <div class="integraciones-card__status">
                        <span class="integraciones-card__dot"></span>
                        <span>No conectado</span>
                    </div>
                </div>
                <div class="integraciones-card__overlay">
                    <div class="integraciones-card__badge">
                        <i class="fa-solid fa-lock"></i> Próximamente
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($clientes->count() > 6)
        <script>
            // Lightweight vanilla-JS search filter — no dedicated JS entry point
            // needed for this page (no AJAX, nothing else for JS to do here).
            (function () {
                var input = document.getElementById('integracionClienteSearch');
                var grid = document.querySelector('[data-integracion-cliente-grid]');
                var empty = document.querySelector('[data-integracion-cliente-empty]');
                if (!input || !grid) return;

                input.addEventListener('input', function () {
                    var term = input.value.trim().toLowerCase();
                    var cards = grid.querySelectorAll('[data-integracion-cliente-card]');
                    var visibleCount = 0;

                    cards.forEach(function (card) {
                        var matches = card.getAttribute('data-search').indexOf(term) !== -1;
                        card.classList.toggle('hidden', !matches);
                        if (matches) visibleCount++;
                    });

                    if (empty) empty.hidden = visibleCount !== 0;
                });
            })();
        </script>
    @endif
@endsection
