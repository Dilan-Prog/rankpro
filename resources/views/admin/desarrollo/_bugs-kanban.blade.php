{{--
    Bugs Kanban (Variant B) — free drag between any column, no business
    gate. Drop just PUTs the new estado to the existing bugs.update route.
--}}
@if ($bugs->isEmpty())
    <div class="empty-state" data-bugs-kanban-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-bug"></i></div>
        <p class="empty-state__text">Sin bugs registrados todavía.</p>
    </div>
@else
    @php
        $estados = ['abierto' => 'Abierto', 'en_progreso' => 'En Progreso', 'resuelto' => 'Resuelto'];
        $porEstado = $bugs->groupBy(fn ($b) => $b->estado->value);
    @endphp
    <div class="kanban" data-bugs-kanban data-update-base="{{ url('admin/desarrollo/bugs') }}">
        @foreach ($estados as $valor => $label)
            @php $enEstado = $porEstado->get($valor, collect()); @endphp
            <div class="kanban__column" data-estado="{{ $valor }}">
                <div class="kanban__column-header">
                    <span>{{ $label }}</span>
                    <span class="kanban__count" data-kanban-count>{{ $enEstado->count() }}</span>
                </div>
                <div class="kanban__column-body" data-kanban-dropzone>
                    @foreach ($enEstado as $bug)
                        @include('admin.desarrollo._bug-kanban-card', ['bug' => $bug])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
