@if ($registros->isEmpty())
    <div class="empty-state" data-qa-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-vial"></i></div>
        <p class="empty-state__text">Sin pruebas QA registradas todavía.</p>
    </div>
@endif
<div class="table-wrap" data-qa-table {{ $registros->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Tipo de prueba</th>
                <th>Resultado</th>
                <th>Notas</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-qa-rows>
            @foreach ($registros as $qa)
                @include('admin.desarrollo._qa-row', ['qa' => $qa])
            @endforeach
        </tbody>
    </table>
</div>
