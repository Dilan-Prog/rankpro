@if ($tareas->isEmpty())
    <div class="empty-state" data-tareas-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-list-check"></i></div>
        <p class="empty-state__text">Sin tareas registradas todavía.</p>
    </div>
@endif
<div class="table-wrap" data-tareas-table {{ $tareas->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Responsable</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Fecha límite</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-tareas-rows>
            @foreach ($tareas as $tarea)
                @include('admin.desarrollo._tarea-row', ['tarea' => $tarea])
            @endforeach
        </tbody>
    </table>
</div>
