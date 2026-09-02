<div class="empty-state" data-contenido-empty {{ $contenidos->isEmpty() ? '' : 'hidden' }}>
    <div class="empty-state__icon"><i class="fa-solid fa-file-lines"></i></div>
    <p class="empty-state__text">Sin contenido registrado todavía.</p>
</div>
<div class="table-wrap" data-contenido-table data-paginate="15" {{ $contenidos->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Keyword objetivo</th>
                <th>URL</th>
                <th>Tráfico generado</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-contenido-rows>
            @foreach ($contenidos as $ct)
                @include('admin.seo._contenido-row', ['ct' => $ct])
            @endforeach
        </tbody>
    </table>
</div>
