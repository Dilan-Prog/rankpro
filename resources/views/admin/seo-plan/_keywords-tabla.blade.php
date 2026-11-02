{{--
    Keywords de un clúster (o las sueltas). Posición actual/anterior solo se
    leen: la captura vive en Keywords. El select reasigna la keyword por AJAX.
--}}
@if ($kws->isEmpty())
    <div class="empty-state"><p class="empty-state__text">Sin keywords en este clúster todavía. Cámbialas de clúster con el selector de cualquier fila.</p></div>
@else
    <x-data-table :headers="['Keyword', 'Intención', 'Posición', 'Anterior', 'Cambio', 'Clúster']">
        @foreach ($kws as $k)
            @php
                $pos = $posiciones[$k->id] ?? ['actual' => null, 'anterior' => null];
                // Subir en el ranking = número más bajo: anterior - actual positivo es mejora.
                $cambio = ($pos['actual'] !== null && $pos['anterior'] !== null) ? $pos['anterior'] - $pos['actual'] : null;
            @endphp
            <tr>
                <td>{{ $k->keyword }}</td>
                <td>{{ $k->intencion ?: '—' }}</td>
                <td class="u-mono">{{ $pos['actual'] ?? '—' }}</td>
                <td class="u-mono">{{ $pos['anterior'] ?? '—' }}</td>
                <td class="u-mono">
                    @if ($cambio === null || $cambio === 0)
                        <span class="field__hint">{{ $cambio === 0 ? '=' : '—' }}</span>
                    @elseif ($cambio > 0)
                        <span class="spl-delta spl-delta--sube"><i class="fa-solid fa-arrow-up"></i> {{ $cambio }}</span>
                    @else
                        <span class="spl-delta spl-delta--baja"><i class="fa-solid fa-arrow-down"></i> {{ abs($cambio) }}</span>
                    @endif
                </td>
                <td>
                    <select class="select spl-select-cluster" aria-label="Clúster de {{ $k->keyword }}" data-spl-asignar data-keyword="{{ $k->id }}">
                        <option value="">— Sin clúster —</option>
                        @foreach ($clusters as $op)
                            <option value="{{ $op->id }}" @selected($k->cluster_id === $op->id)>{{ $op->nombre }}</option>
                        @endforeach
                    </select>
                </td>
            </tr>
        @endforeach
    </x-data-table>
@endif
