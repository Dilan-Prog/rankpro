{{--
    Tarjetas selectoras de cuenta (una por cliente con servicio SEO).
    $editable: en el índice llevan editar/eliminar (el modal solo existe ahí);
    $seleccionId: id de campaña resaltada en el detalle; $fila: una sola fila con scroll horizontal.
    seo.js reconstruye estas tarjetas tras editar/eliminar: mantener el marcado en sincronía.
--}}
<div class="seo-cuentas {{ ($fila ?? false) ? 'seo-cuentas--fila' : '' }}" data-seo-cliente-grid role="list" aria-label="Cuentas con SEO">
    @foreach ($cuentas as $c)
        @php
            $activa = ($seleccionId ?? null) !== null && (int) $c['campana_id'] === (int) $seleccionId;
        @endphp
        <div class="seo-cuenta {{ $activa ? 'is-active' : '' }}" role="listitem" data-seo-cliente-card data-cliente-id="{{ $c['cliente_id'] }}" data-search="{{ mb_strtolower($c['cliente'].' '.($c['contacto'] ?? '').' '.($c['responsable'] ?? '')) }}" @if ($editable ?? false) data-cliente="{{ json_encode($c) }}" @endif>
            @if ($c['campana_id'])
                <a href="{{ $c['show_url'] }}" class="seo-cuenta__link" @if ($activa) aria-current="page" @endif>
                    <span class="seo-cuenta__head">
                        <span class="seo-cuenta__avatar" aria-hidden="true">{{ \App\Support\Labels::initials($c['cliente']) }}</span>
                        <span class="seo-cuenta__id">
                            <span class="seo-cuenta__name">{{ $c['cliente'] }}</span>
                            <span class="seo-cuenta__resp">{{ $c['responsable'] ?: 'Sin responsable' }}</span>
                        </span>
                    </span>
                    <span class="seo-cuenta__foot">
                        <x-badge :status="$c['estado']" />
                        <span class="seo-cuenta__score" title="SEO Score de la auditoría">Score {{ $c['seo_score'] ?? '—' }}</span>
                    </span>
                </a>
                @if ($editable ?? false)
                    <span class="seo-cuenta__acciones">
                        <button type="button" class="btn--icon" title="Editar campaña" aria-label="Editar campaña de {{ $c['cliente'] }}" data-edit-seo-cliente><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn--icon" title="Eliminar campaña" aria-label="Eliminar campaña de {{ $c['cliente'] }}" style="color:var(--text-danger);" data-delete-seo-cliente><i class="fa-solid fa-trash"></i></button>
                    </span>
                @endif
            @else
                <div class="seo-cuenta__link seo-cuenta__link--vacia">
                    <span class="seo-cuenta__head">
                        <span class="seo-cuenta__avatar" aria-hidden="true">{{ \App\Support\Labels::initials($c['cliente']) }}</span>
                        <span class="seo-cuenta__id">
                            <span class="seo-cuenta__name">{{ $c['cliente'] }}</span>
                            <span class="seo-cuenta__resp">{{ $c['responsable'] ?: 'Sin responsable' }}</span>
                        </span>
                    </span>
                    <span class="seo-cuenta__foot">
                        <span class="seo-cuenta__resp">Sin campaña</span>
                        <button type="button" class="btn btn--secondary btn--sm" data-open-seo-modal data-preselect-cliente="{{ $c['cliente_id'] }}">
                            <i class="fa-solid fa-plus"></i> Crear campaña
                        </button>
                    </span>
                </div>
            @endif
        </div>
    @endforeach
</div>
