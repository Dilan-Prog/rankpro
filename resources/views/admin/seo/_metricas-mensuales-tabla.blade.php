<div class="empty-state" data-metricas-empty {{ $metricas->isEmpty() ? '' : 'hidden' }}>
    <div class="empty-state__icon"><i class="fa-solid fa-chart-line"></i></div>
    <p class="empty-state__text">Sin métricas mensuales capturadas todavía — agrega el primer mes para empezar a graficar la tendencia.</p>
</div>
<div class="table-wrap" data-metricas-table data-paginate="15" {{ $metricas->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Mes</th>
                <th>Ciclo</th>
                <th>Tráfico Orgánico</th>
                <th>Top 3 / Top 10 / Top 100</th>
                <th>Backlinks</th>
                <th>Errores (resueltos/pend.)</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-metricas-rows>
            @foreach ($metricas as $m)
                @include('admin.seo._metrica-mensual-row', ['m' => $m])
            @endforeach
        </tbody>
    </table>
</div>
