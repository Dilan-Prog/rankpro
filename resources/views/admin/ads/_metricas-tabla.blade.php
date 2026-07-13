@if ($metricas->isEmpty())
    <div class="empty-state" data-metricas-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-chart-column"></i></div>
        <p class="empty-state__text">Sin métricas registradas todavía.</p>
    </div>
@endif
<div class="table-wrap" data-metricas-table {{ $metricas->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Periodo</th>
                <th>Inversión</th>
                <th>Impr.</th>
                <th>Clics</th>
                <th>CTR</th>
                <th>CPC</th>
                <th>Conv.</th>
                <th>CPL</th>
                <th>CPA</th>
                <th>ROAS</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-metricas-rows>
            @foreach ($metricas->sortByDesc(fn ($m) => $m->anio * 100 + $m->mes) as $m)
                @include('admin.ads._metrica-row', ['m' => $m])
            @endforeach
        </tbody>
    </table>
</div>
