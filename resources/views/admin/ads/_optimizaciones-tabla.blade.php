@if ($optimizaciones->isEmpty())
    <div class="empty-state" data-optimizaciones-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-sliders"></i></div>
        <p class="empty-state__text">Sin optimizaciones registradas todavía.</p>
    </div>
@endif
<div class="table-wrap" data-optimizaciones-table {{ $optimizaciones->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Descripción del cambio</th>
                <th>Resultado obtenido</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-optimizaciones-rows>
            @foreach ($optimizaciones as $o)
                @include('admin.ads._optimizacion-row', ['o' => $o])
            @endforeach
        </tbody>
    </table>
</div>
