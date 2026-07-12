@if ($posiciones->isEmpty())
    <div class="empty-state" data-posiciones-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-ranking-star"></i></div>
        <p class="empty-state__text">Sin posiciones registradas todavía.</p>
    </div>
@endif
<div class="table-wrap" data-posiciones-table {{ $posiciones->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Keyword</th>
                <th>URL</th>
                <th>Posición</th>
                <th>Anterior</th>
                <th>Variación</th>
                <th>Volumen</th>
                <th>KD</th>
                <th>Dispositivo</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-posiciones-rows>
            @foreach ($posiciones as $p)
                @include('admin.seo._posicion-row', ['p' => $p])
            @endforeach
        </tbody>
    </table>
</div>
