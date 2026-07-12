@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $campana->nombre }}</h1>
            <p class="page-header__subtitle">{{ $campana->cliente->nombre }} · {{ \App\Support\Labels::plataforma($campana->plataforma) }}</p>
        </div>
        <div style="display:flex; gap: var(--space-2);">
            <a href="{{ route('admin.ads.edit', $campana) }}" class="btn btn--secondary">
                <i class="fa-solid fa-pen"></i> Editar
            </a>
            <a href="{{ route('admin.ads.index', ['plataforma' => $campana->plataforma]) }}" class="btn btn--secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a Ads
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="kpi-grid">
        <x-stat-card label="Objetivo" value="{{ \App\Support\Labels::objetivo($campana->objetivo) }}" icon="fa-bullseye" color="primary" />
        <x-stat-card label="Presupuesto Mensual" value="${{ number_format($campana->presupuesto_mensual) }}" icon="fa-dollar-sign" color="teal" />
        <x-stat-card label="Estado" value="{{ ucfirst($campana->estado->value) }}" icon="fa-circle-check" color="emerald" />
        <x-stat-card label="Creativos" value="{{ $campana->creativos->count() }}" icon="fa-images" color="blue" />
    </div>

    <div class="card card--padded" style="margin-bottom: var(--space-6);">
        <h3 style="margin-bottom: var(--space-3);">Detalles</h3>
        <p style="font-size:var(--text-sm); color:var(--color-muted-foreground);">
            Inicio: {{ $campana->fecha_inicio?->format('Y-m-d') ?? '—' }} &middot;
            Fin: {{ $campana->fecha_fin?->format('Y-m-d') ?? '—' }}
        </p>
        @if ($campana->notas)
            <p style="font-size:var(--text-sm); color:var(--color-muted-foreground); margin-top: var(--space-2);">{{ $campana->notas }}</p>
        @endif
    </div>

    <div class="card">
        <div class="card__header">
            <h2 class="card__header-title">Creativos</h2>
            <button type="button" class="btn btn--ghost" onclick="window.AgencyOS.openModal('creativoModal')">
                <i class="fa-solid fa-plus"></i> Agregar Creativo
            </button>
        </div>
        @if ($campana->creativos->isEmpty())
            <div class="empty-state">
                <div class="empty-state__icon"><i class="fa-solid fa-images"></i></div>
                <p class="empty-state__text">Sin creativos registrados todavía.</p>
            </div>
        @else
            <x-data-table :headers="['Título', 'Tipo', 'CTR', 'Estado', 'A/B Testing', '']">
                @foreach ($campana->creativos as $creativo)
                    <tr>
                        <td><div style="font-weight:500">{{ $creativo->titulo }}</div></td>
                        <td><span style="text-transform:capitalize; font-size:var(--text-xs); color:var(--color-muted-foreground);">{{ $creativo->tipo }}</span></td>
                        <td class="u-mono">{{ $creativo->ctr ? $creativo->ctr.'%' : '—' }}</td>
                        <td><x-badge :status="$creativo->estado" /></td>
                        <td>{{ $creativo->ab_testing ? 'Sí' : 'No' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.ads.creativos.destroy', $creativo) }}" data-confirm="¿Eliminar este creativo?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn--icon" title="Eliminar" style="color:var(--text-danger);"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        @endif
    </div>

    <x-modal id="creativoModal">
        <x-slot:header><h2>Agregar Creativo</h2></x-slot:header>
        <form method="POST" action="{{ route('admin.ads.creativos.store', $campana) }}">
            @csrf
            <div class="field">
                <label class="field__label" for="titulo">Título</label>
                <input class="input" type="text" name="titulo" id="titulo" required>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="copy">Copy</label>
                <textarea class="textarea" name="copy" id="copy"></textarea>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="tipo">Tipo</label>
                    <select class="select" name="tipo" id="tipo" required>
                        <option value="imagen">Imagen</option>
                        <option value="video">Video</option>
                        <option value="carrusel">Carrusel</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="estado">Estado</label>
                    <select class="select" name="estado" id="estado" required>
                        <option value="activo">Activo</option>
                        <option value="pausado">Pausado</option>
                    </select>
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-3);">
                <div class="field">
                    <label class="field__label" for="ctr">CTR estimado (%)</label>
                    <input class="input" type="number" step="0.001" min="0" name="ctr" id="ctr">
                </div>
                <div class="field" style="justify-content:flex-end;">
                    <label class="checkbox-item">
                        <input type="checkbox" name="ab_testing" value="1">
                        Incluir en A/B testing
                    </label>
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-3);">
                <label class="field__label" for="url_imagen">URL de imagen/video</label>
                <input class="input" type="text" name="url_imagen" id="url_imagen">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Agregar</button>
                <button type="button" class="btn btn--secondary" data-modal-close="creativoModal">Cancelar</button>
            </div>
        </form>
    </x-modal>
@endsection
