{{--
    Desarrollo index — Kanban view (Variant A). Columns are every
    FaseProyecto case in order (Planeación..Control, plus the Cerrado
    terminus so closed proyectos still have a home on the board instead of
    disappearing). data-orden drives the adjacent-column-only drag gate in
    desarrollo.js: a card may only drop one column left (retroceder, no
    gate) or one column right (aprobar, gated on the phase's checklist).
--}}
@php
    $fases = \App\Enums\FaseProyecto::cases();
    $proyectosPorFase = $proyectos->groupBy('fase_actual');
@endphp
<div class="kanban" data-proyecto-kanban
    data-aprobar-base="{{ url('admin/desarrollo') }}"
    data-retroceder-base="{{ url('admin/desarrollo') }}">
    @foreach ($fases as $faseEnum)
        @php $enFase = $proyectosPorFase->get($faseEnum->value, collect()); @endphp
        <div class="kanban__column" data-fase="{{ $faseEnum->value }}" data-orden="{{ $faseEnum->orden() }}">
            <div class="kanban__column-header">
                <span>{{ \App\Support\Labels::faseProyecto($faseEnum->value) }}</span>
                <span class="kanban__count" data-kanban-count>{{ $enFase->count() }}</span>
            </div>
            <div class="kanban__column-body" data-kanban-dropzone>
                @foreach ($enFase as $p)
                    @include('admin.desarrollo._proyecto-kanban-card', ['p' => $p])
                @endforeach
            </div>
        </div>
    @endforeach
</div>
