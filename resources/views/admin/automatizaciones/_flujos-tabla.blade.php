@if ($flujos->isEmpty())
    <div class="empty-state" data-flujos-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-diagram-project"></i></div>
        <p class="empty-state__text">Sin flujos registrados todavía.</p>
    </div>
@endif
<div class="table-wrap" data-flujos-table {{ $flujos->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Flujo</th>
                <th>Tipo</th>
                <th>Complejidad</th>
                <th>Integraciones</th>
                <th>Horas/Mes</th>
                <th>Mensajes/Mes</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-flujos-rows>
            @foreach ($flujos as $fl)
                @include('admin.automatizaciones._flujo-row', ['fl' => $fl])
            @endforeach
        </tbody>
    </table>
</div>
