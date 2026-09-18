@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/correo-envios.css')
@endsection

@php
    $estado = $row['estado'];
    $editable = $row['editable'];
    $borrable = in_array($estado, ['borrador', 'cancelado', 'fallido'], true);
    $fechaLabel = match ($estado) {
        'programado' => 'Programado para',
        'enviado', 'enviando', 'fallido' => 'Enviado el',
        default => 'Última edición',
    };
    $fechaValor = match ($estado) {
        'programado' => $row['programado_para'],
        'enviado', 'enviando', 'fallido' => $row['enviado_en'],
        default => $envio->updated_at?->format('Y-m-d H:i'),
    };
@endphp

@section('content')
    <div class="page-header">
        <div>
            <p class="page-header__subtitle" style="margin-bottom:2px;">{{ $row['plantilla'] ?? 'Plantilla eliminada' }}</p>
            <h1 class="page-header__title">{{ $envio->asunto }}</h1>
            <p class="page-header__subtitle">
                De {{ $envio->remitente_nombre ?: config('mail.from.name') }}
                &lt;{{ $envio->remitente_email ?: config('mail.from.address') }}&gt;
                · responsable {{ $row['responsable'] ?? '—' }}
            </p>
        </div>
        <div class="correo-header-actions"
             data-correo-envio-show
             data-envio-id="{{ $envio->id }}"
             data-enviar-url="{{ route('admin.correo.envios.enviar', $envio) }}"
             data-cancelar-url="{{ route('admin.correo.envios.cancelar', $envio) }}"
             data-destroy-url="{{ route('admin.correo.envios.destroy', $envio) }}"
             data-index-url="{{ route('admin.correo.envios.index') }}"
             data-destinatarios="{{ $row['destinatarios'] }}">
            <x-badge :status="$estado" data-estado-badge />
            @if ($editable)
                <a href="{{ route('admin.correo.envios.edit', $envio) }}" class="btn btn--secondary">
                    <i class="fa-solid fa-pen"></i> Editar
                </a>
                <button type="button" class="btn btn--primary" data-enviar-ahora>
                    <i class="fa-solid fa-paper-plane"></i> Enviar ahora
                </button>
            @endif
            @if ($estado === 'programado')
                <button type="button" class="btn btn--secondary" data-cancelar-programacion>
                    <i class="fa-solid fa-ban"></i> Cancelar programación
                </button>
            @endif
            @if ($borrable)
                <button type="button" class="btn btn--danger" data-eliminar-envio>
                    <i class="fa-solid fa-trash"></i> Eliminar
                </button>
            @endif
            <a href="{{ route('admin.correo.envios.index') }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    {{-- ---------- KPIs del envío ---------- --}}
    <div class="kpi-grid">
        <x-stat-card label="Destinatarios" value="{{ $row['destinatarios'] }}" sub="en la lista" icon="fa-users" color="primary" />
        <x-stat-card label="Enviados" value="{{ $row['enviados'] }}" sub="{{ $row['fallidos'] }} fallidos" icon="fa-paper-plane" color="{{ $row['fallidos'] > 0 ? 'red' : 'teal' }}" />
        <x-stat-card label="Apertura estimada" value="{{ $row['apertura'] !== null && $estado === 'enviado' ? $row['apertura'].'%' : '—' }}" sub="{{ $row['abiertos'] }} con al menos una apertura" icon="fa-envelope-open" color="blue" />
        <x-stat-card label="No abiertos" value="{{ $estado === 'enviado' ? $row['no_abiertos'] : '—' }}" sub="enviados sin apertura registrada" icon="fa-envelope" color="amber" />
        <x-stat-card label="Clics" value="{{ $row['clics'] }}" sub="en enlaces del correo" icon="fa-arrow-pointer" color="emerald" />
    </div>

    <div class="correo-show">
        <div class="correo-show__principal">
            {{-- Ficha --}}
            <div class="card card--padded">
                <h3 class="card__header-title">Datos del envío</h3>
                <dl class="correo-ficha">
                    <dt>Estado</dt><dd><x-badge :status="$estado" /></dd>
                    <dt>{{ $fechaLabel }}</dt><dd class="u-mono">{{ $fechaValor ?? '—' }}</dd>
                    <dt>Plantilla</dt>
                    <dd>
                        @if ($envio->plantilla && ! $envio->plantilla->trashed())
                            <a href="{{ route('admin.correo.plantillas.show', $envio->plantilla) }}">{{ $envio->plantilla->nombre }}</a>
                        @else
                            {{ $row['plantilla'] ?? '—' }} <span class="field__hint" style="display:inline">(eliminada; se conserva el HTML enviado)</span>
                        @endif
                    </dd>
                    <dt>Remitente</dt><dd class="u-mono">{{ $envio->remitente_nombre ?: config('mail.from.name') }} &lt;{{ $envio->remitente_email ?: config('mail.from.address') }}&gt;</dd>
                    <dt>Responsable</dt><dd>{{ $row['responsable'] ?? '—' }}</dd>
                    <dt>Creado</dt><dd class="u-mono">{{ $envio->created_at?->format('Y-m-d H:i') }}</dd>
                </dl>

                @if (! empty($variables))
                    <h4 class="correo-ficha__sub">Variables del envío</h4>
                    <dl class="correo-ficha">
                        @foreach ($variables as $clave => $valor)
                            @php($marcador = sprintf('{{%s}}', $clave))
                            <dt><code>{{ $marcador }}</code> {{ $catalogo[$clave]['label'] ?? '' }}</dt>
                            <dd>{{ $valor }}</dd>
                        @endforeach
                    </dl>
                @endif
            </div>

            {{-- Destinatarios --}}
            <div class="card">
                <div class="card__header">
                    <h3 class="card__header-title">Destinatarios</h3>
                    <span class="field__hint">{{ $row['destinatarios'] }} en total</span>
                </div>
                <x-data-table :headers="['Correo', 'Cliente', 'Estado', 'Enviado', 'Aperturas', 'Clics', 'Actividad reciente']" data-paginate="25">
                    @forelse ($destinatarios as $d)
                        <tr>
                            <td>
                                <div style="font-weight:600">{{ $d['nombre'] ?: '—' }}</div>
                                <div class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $d['email'] }}</div>
                            </td>
                            <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground)">{{ $d['cliente'] ?? 'Correo suelto' }}</span></td>
                            <td>
                                <x-badge :status="$d['estado']" />
                                @if ($d['error'])
                                    <div class="correo-error" title="{{ $d['error'] }}">{{ \Illuminate\Support\Str::limit($d['error'], 80) }}</div>
                                @endif
                            </td>
                            <td><span class="u-mono" style="font-size:var(--text-xs)">{{ $d['enviado_en'] ?? '—' }}</span></td>
                            <td>
                                <span class="u-mono">{{ $d['aperturas'] }}</span>
                                @if ($d['primera_apertura_en'])
                                    <div class="u-mono" style="font-size:var(--text-xs); color:var(--color-muted-foreground)">1.ª {{ $d['primera_apertura_en'] }}</div>
                                @endif
                            </td>
                            <td><span class="u-mono">{{ $d['clics'] }}</span></td>
                            <td>
                                @if (empty($d['eventos']))
                                    <span style="color:var(--color-muted-foreground)">—</span>
                                @else
                                    <ul class="correo-eventos">
                                        @foreach ($d['eventos'] as $ev)
                                            <li>
                                                <i class="fa-solid {{ $ev['tipo'] === 'clic' ? 'fa-arrow-pointer' : 'fa-envelope-open' }}"></i>
                                                <span class="u-mono">{{ $ev['fecha'] }}</span>
                                                @if ($ev['tipo'] === 'clic' && $ev['url'])
                                                    <a href="{{ $ev['url'] }}" target="_blank" rel="noopener noreferrer nofollow" title="{{ $ev['url'] }}">{{ \Illuminate\Support\Str::limit($ev['url'], 36) }}</a>
                                                @else
                                                    <span>{{ $ev['tipo'] === 'clic' ? 'clic' : 'apertura' }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table__empty">Este envío aún no tiene destinatarios.</td></tr>
                    @endforelse
                </x-data-table>
                <p class="correo-nota" style="margin: var(--space-3) var(--space-5) var(--space-4);">
                    <i class="fa-solid fa-circle-info"></i>
                    La apertura es una estimación: algunos clientes de correo bloquean o precargan imágenes. Los clics sí son exactos.
                </p>
            </div>
        </div>

        {{-- Vista previa del HTML enviado (o de la plantilla si aún no salió) --}}
        <aside class="correo-show__previa">
            <div class="card correo-previa">
                <div class="correo-previa__head">
                    <span class="correo-previa__titulo">
                        <i class="fa-solid fa-eye"></i>
                        {{ $envio->html_congelado !== null ? 'Correo tal como se envió' : 'Previa de la plantilla' }}
                    </span>
                    <div class="correo-previa__dispositivos">
                        <button type="button" class="is-active" data-previa-ancho="600" title="Escritorio"><i class="fa-solid fa-desktop"></i></button>
                        <button type="button" data-previa-ancho="390" title="Móvil"><i class="fa-solid fa-mobile-screen"></i></button>
                    </div>
                </div>
                <div class="correo-previa__lienzo">
                    @if ($html !== null)
                        <iframe class="correo-previa__frame" data-previa-frame title="Vista previa del correo" sandbox="allow-same-origin" style="width:600px" srcdoc="{{ $html }}"></iframe>
                    @else
                        <p class="correo-previa__estado">No hay HTML que mostrar: la plantilla fue eliminada antes de enviar.</p>
                    @endif
                </div>
                @if ($envio->html_congelado === null)
                    <p class="correo-previa__nota">Las variables de persona se resuelven por destinatario al enviar; aquí van vacías.</p>
                @endif
            </div>
        </aside>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/correo-envios.js')
@endsection
