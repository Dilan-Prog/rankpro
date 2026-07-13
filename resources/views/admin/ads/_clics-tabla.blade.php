@if ($clics->isEmpty())
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-arrow-pointer"></i></div>
        <p class="empty-state__text">Sin clics registrados todavía.</p>
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Identificador</th>
                    <th>UTM Campaign</th>
                    <th>Campaña</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clics as $clic)
                    <tr>
                        <td class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $clic->created_at->format('Y-m-d H:i') }}</td>
                        <td><span class="badge badge--info">{{ $clic->gclid ? 'GCLID' : ($clic->gbraid ? 'GBRAID' : ($clic->wbraid ? 'WBRAID' : '—')) }}</span></td>
                        <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $clic->utm_campaign ?? '—' }}</span></td>
                        <td>
                            @if ($clic->ads_campana_id === $campana->id)
                                <span class="badge badge--success">Esta campaña</span>
                            @else
                                <span class="badge badge--neutral">Sin asignar</span>
                            @endif
                        </td>
                        <td>
                            @if ($clic->ads_campana_id !== $campana->id)
                                <form method="POST" action="{{ route('admin.clientes.clics.asignar', ['cliente' => $campana->cliente_id, 'clic' => $clic]) }}">
                                    @csrf
                                    <input type="hidden" name="ads_campana_id" value="{{ $campana->id }}">
                                    <button type="submit" class="btn--icon" title="Asignar a esta campaña"><i class="fa-solid fa-link"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
