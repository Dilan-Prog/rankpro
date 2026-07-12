@if ($bugs->isEmpty())
    <div class="empty-state" data-bugs-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-bug"></i></div>
        <p class="empty-state__text">Sin bugs registrados todavía.</p>
    </div>
@endif
<div class="table-wrap" data-bugs-table {{ $bugs->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Resuelto</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-bugs-rows>
            @foreach ($bugs as $bug)
                @include('admin.desarrollo._bug-row', ['bug' => $bug])
            @endforeach
        </tbody>
    </table>
</div>
