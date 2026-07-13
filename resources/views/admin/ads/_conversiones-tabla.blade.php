@if ($conversiones->isEmpty())
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-bullseye"></i></div>
        <p class="empty-state__text">Sin conversiones registradas todavía.</p>
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($conversiones as $conversion)
                    <tr>
                        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $conversion->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ \App\Support\Labels::tipoConversion($conversion->tipo->value) }}</td>
                        <td class="u-mono">{{ $conversion->valor ? '$'.number_format($conversion->valor, 2) : '—' }}</td>
                        <td><x-badge :status="$conversion->estado" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
