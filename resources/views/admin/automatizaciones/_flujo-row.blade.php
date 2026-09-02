@php $integracionesTexto = implode(', ', $fl->integraciones ?? []); @endphp
<tr data-flujo-id="{{ $fl->id }}"
    data-flujo-nombre="{{ $fl->nombre }}"
    data-flujo-tipo="{{ $fl->tipo }}"
    data-flujo-complejidad="{{ $fl->complejidad }}"
    data-flujo-integraciones="{{ $integracionesTexto }}"
    data-flujo-horas-ahorradas-mes="{{ $fl->horas_ahorradas_mes }}"
    data-flujo-mensajes-gestionados-mes="{{ $fl->mensajes_gestionados_mes }}"
    data-flujo-estado="{{ $fl->estado }}"
    data-flujo-fecha-implementado="{{ optional($fl->fecha_implementado)->format('Y-m-d') }}"
    data-flujo-notas="{{ $fl->notas }}">
    <td>{{ $fl->nombre }}</td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ \App\Support\Labels::tipoFlujoAutomatizacion($fl->tipo) }}</span></td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ \App\Support\Labels::complejidadFlujoAutomatizacion($fl->complejidad) }}</span></td>
    <td><span style="font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $integracionesTexto ?: '—' }}</span></td>
    <td class="u-mono">{{ $fl->horas_ahorradas_mes ?? '—' }}</td>
    <td class="u-mono">{{ $fl->mensajes_gestionados_mes !== null ? number_format($fl->mensajes_gestionados_mes) : '—' }}</td>
    <td><x-badge :status="$fl->estado" /></td>
    <td>
        <div style="display:flex; gap:4px;">
            <button type="button" class="btn--icon" title="Editar" data-edit-flujo="{{ $fl->id }}">
                <i class="fa-solid fa-pen"></i>
            </button>
            <button type="button" class="btn--icon" title="Eliminar" style="color:var(--text-danger);" data-delete-flujo="{{ $fl->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
