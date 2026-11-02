{{--
    Proyecto card for the Desarrollo index Kanban (Variant A). $p is one row
    of the mapped array DesarrolloController@index already builds for the
    list view (same fields, no extra query). data-fase mirrors the column
    the card currently sits in and is kept in sync by desarrollo.js after a
    successful drag so later drags compute adjacency off the live value.
--}}
<div class="kanban__card" draggable="true" data-kanban-card
    data-proyecto-id="{{ $p['id'] }}"
    data-fase="{{ $p['fase_actual'] }}"
    data-avance-origen="{{ $p['avance_origen'] ?? 'fase' }}">
    <div class="kanban__card-top">
        <span class="kanban__card-icon" style="background: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary);">
            <i class="fa-solid fa-diagram-project"></i>
        </span>
        <span class="kanban__card-title" title="{{ $p['nombre'] }}">{{ $p['nombre'] }}</span>
        @if (! empty($p['url_repositorio']))
            <a href="{{ $p['url_repositorio'] }}" target="_blank" rel="noopener" class="kanban__card-date" title="Repositorio"><i class="fa-solid fa-code-branch"></i></a>
        @endif
        <a href="{{ route('admin.desarrollo.show', $p['id']) }}" class="kanban__card-date" title="Ver proyecto">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </a>
    </div>
    <div class="kanban__card-meta">
        <span title="{{ $p['cliente'] }}">{{ $p['cliente'] }}</span>
        <span data-kanban-card-estado><x-badge :status="$p['estado']" /></span>
    </div>
    @if (! empty($p['stack']))
        <div class="proyecto-chips">
            @foreach (array_slice($p['stack'], 0, 3) as $tecnologia)
                <span class="proyecto-chip">{{ $tecnologia }}</span>
            @endforeach
        </div>
    @endif
    <div class="progress-bar" style="margin: var(--space-2) 0 4px;">
        <div class="progress-bar__fill" style="width:{{ $p['porcentaje_avance'] }}%;" data-kanban-card-progress-fill></div>
    </div>
    <div class="kanban__card-meta">
        <span class="u-mono" data-kanban-card-porcentaje>{{ $p['porcentaje_avance'] }}%</span>
        @if (($p['dias_restantes'] ?? null) !== null)
            <span class="u-mono" style="{{ $p['dias_restantes'] < 0 ? 'color:var(--text-danger)' : '' }}">
                {{ $p['dias_restantes'] < 0 ? 'Vencida '.abs($p['dias_restantes']).'d' : ($p['dias_restantes'] === 0 ? 'Entrega hoy' : $p['dias_restantes'].'d') }}
            </span>
        @endif
        <span class="u-mono" style="color:var(--text-success)">${{ number_format($p['pagos_recibidos']) }}</span>
    </div>
</div>
