{{--
    Tareas Kanban (Variant B) — free drag between any column, no business
    gate (unlike the proyecto-fase board). Drop just PUTs the new estado to
    the existing tareas.update route, same endpoint the edit modal uses.
--}}
@if ($tareas->isEmpty())
    <div class="empty-state" data-tareas-kanban-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-list-check"></i></div>
        <p class="empty-state__text">Sin tareas registradas todavía.</p>
    </div>
@else
    @php
        $estados = ['pendiente' => 'Pendiente', 'en_progreso' => 'En Progreso', 'completada' => 'Completada'];
        $porEstado = $tareas->groupBy(fn ($t) => $t->estado->value);
    @endphp
    <div class="kanban" data-tareas-kanban data-update-base="{{ url('admin/desarrollo/tareas') }}">
        @foreach ($estados as $valor => $label)
            @php $enEstado = $porEstado->get($valor, collect()); @endphp
            <div class="kanban__column" data-estado="{{ $valor }}">
                <div class="kanban__column-header">
                    <span>{{ $label }}</span>
                    <span class="kanban__count" data-kanban-count>{{ $enEstado->count() }}</span>
                </div>
                <div class="kanban__column-body" data-kanban-dropzone>
                    @foreach ($enEstado as $tarea)
                        @include('admin.desarrollo._tarea-kanban-card', ['tarea' => $tarea])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
