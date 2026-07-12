@if ($backlinks->isEmpty())
    <div class="empty-state" data-backlinks-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-link"></i></div>
        <p class="empty-state__text">Sin backlinks registrados todavía.</p>
    </div>
@endif
<div class="table-wrap" data-backlinks-table {{ $backlinks->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>URL Destino</th>
                <th>Dominio Referente</th>
                <th>DA/DR</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-backlinks-rows>
            @foreach ($backlinks as $b)
                @include('admin.seo._backlink-row', ['b' => $b])
            @endforeach
        </tbody>
    </table>
</div>
