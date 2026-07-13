@if ($creativos->isEmpty())
    <div class="empty-state" data-creativos-empty>
        <div class="empty-state__icon"><i class="fa-solid fa-images"></i></div>
        <p class="empty-state__text">Sin creativos todavía.</p>
    </div>
@endif
<div class="table-wrap" data-creativos-table {{ $creativos->isEmpty() ? 'hidden' : '' }}>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Copy</th>
                <th>Tipo</th>
                <th>URL</th>
                <th>A/B</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-creativos-rows>
            @foreach ($creativos as $cr)
                @include('admin.ads._creativo-row', ['cr' => $cr])
            @endforeach
        </tbody>
    </table>
</div>
