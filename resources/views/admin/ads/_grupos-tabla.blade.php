@if ($grupos->isEmpty())
    <div class="empty-state" data-grupos-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-layer-group"></i></div>
        <p class="empty-state__text">Sin grupos de anuncios todavía.</p>
    </div>
@endif
<div class="table-wrap" data-grupos-table {{ $grupos->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Audiencia</th>
                <th>Presupuesto</th>
                <th>Keywords</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-grupos-rows>
            @foreach ($grupos as $g)
                @include('admin.ads._grupo-row', ['g' => $g])
            @endforeach
        </tbody>
    </table>
</div>
