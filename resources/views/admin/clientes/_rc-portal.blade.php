{{--
    Tarjeta «Portal del cliente» del Resumen de cliente. Recibe $portal (App\Support\Portal\Administracion):
    enlaces y últimas visitas, sin tokens ni hashes. El enlace completo solo se ve UNA vez, al crearlo.
--}}
@php
    $estados = ['vigente' => 'Vigente', 'vencido' => 'Vencido', 'revocado' => 'Revocado'];
@endphp
<section class="card portal-adm" aria-labelledby="portalAdmTitulo" data-portal-adm
         data-portal-store-url="{{ route('admin.clientes.portal.store', $cliente) }}"
         data-portal-destroy-url="{{ route('admin.clientes.portal.destroy', ['cliente' => $cliente, 'acceso' => '__ID__']) }}">
    <div class="card__header"><span class="card__header-title" id="portalAdmTitulo">Portal del cliente</span></div>
    <div class="portal-adm__cuerpo">
        <p class="portal-adm__ayuda">Enlace de solo lectura para que el cliente vea sus reportes entregados, propuestas y métricas. No incluye finanzas ni contratos. Vence solo y se puede revocar.</p>

        <form class="portal-adm__form" data-portal-form>
            <div class="portal-adm__campo">
                <label for="portalEtiqueta">Para quién (opcional)</label>
                <input class="input" id="portalEtiqueta" name="etiqueta" type="text" maxlength="120" placeholder="Ej. Gerencia" autocomplete="off">
            </div>
            <div class="portal-adm__campo">
                <label for="portalCorreo">Correo (opcional, informativo)</label>
                <input class="input" id="portalCorreo" name="correo" type="email" maxlength="190" autocomplete="off">
            </div>
            <div class="portal-adm__campo">
                <label for="portalVigencia">Vigencia</label>
                <select class="input" id="portalVigencia" name="vigencia_dias">
                    @foreach (\App\Models\PortalAcceso::VIGENCIAS as $dias)
                        <option value="{{ $dias }}" @selected($dias === \App\Models\PortalAcceso::DIAS_VIGENCIA)>{{ $dias }} días</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn--secondary" data-portal-crear><i class="fa-solid fa-link" aria-hidden="true"></i> Crear enlace</button>
        </form>

        <p class="portal-adm__mensaje" role="status" data-portal-mensaje hidden></p>

        <div class="portal-adm__nuevo" data-portal-nuevo hidden>
            <label for="portalUrl">Copia el enlace ahora; no se vuelve a mostrar</label>
            <div class="portal-adm__nuevo-fila">
                <input class="input u-mono" id="portalUrl" type="text" readonly data-portal-url>
                <button type="button" class="btn btn--secondary btn--sm" data-portal-copiar>Copiar</button>
            </div>
        </div>

        <ul class="portal-adm__lista" data-portal-lista>
            @forelse ($portal['accesos'] as $acceso)
                <li class="portal-adm__fila" data-portal-id="{{ $acceso['id'] }}" data-estado="{{ $acceso['estado'] }}">
                    <span class="portal-adm__estado portal-adm__estado--{{ $acceso['estado'] }}">{{ $estados[$acceso['estado']] }}</span>
                    <span class="portal-adm__meta">
                        <strong>{{ $acceso['etiqueta'] ?: 'Sin etiqueta' }}</strong>@if ($acceso['correo']) · {{ $acceso['correo'] }}@endif
                        <br>vence {{ fecha_mx($acceso['expira_en']) }} · {{ $acceso['accesos'] }} {{ $acceso['accesos'] === 1 ? 'acceso' : 'accesos' }}
                        · {{ $acceso['ultimo_acceso_en'] ? 'último '.fecha_mx($acceso['ultimo_acceso_en'], true) : 'sin accesos aún' }}
                    </span>
                    @if ($acceso['estado'] === 'vigente')
                        <button type="button" class="btn btn--secondary btn--sm" data-portal-revocar>Revocar</button>
                    @endif
                </li>
            @empty
                <li class="portal-adm__vacio" data-portal-vacio>Todavía no hay enlaces para este cliente.</li>
            @endforelse
        </ul>

        <details class="portal-adm__visitas" data-portal-visitas>
            <summary>Últimas visitas ({{ count($portal['visitas']) }})</summary>
            @if ($portal['visitas'] === [])
                <p class="portal-adm__vacio">Nadie ha abierto el portal todavía.</p>
            @else
                <ul>
                    @foreach ($portal['visitas'] as $visita)
                        <li>{{ fecha_mx($visita['fecha'], true) }} · {{ $visita['enlace'] }} · <span class="u-mono">{{ $visita['ruta'] }}</span></li>
                    @endforeach
                </ul>
            @endif
        </details>
    </div>
</section>
