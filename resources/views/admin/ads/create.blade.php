@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/ads.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nueva Campaña</h1>
            <p class="page-header__subtitle">Fase 1 · Briefing y Estrategia — toda campaña arranca aquí</p>
        </div>
        <a href="{{ route('admin.ads.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Ads
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.ads.store') }}">
            @csrf

            <h3 class="fase-form__section-title">Datos generales</h3>
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="cliente_id">Cliente</label>
                    <select class="select" name="cliente_id" id="cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}" @selected((int) old('cliente_id') === $cliente->id)>{{ $cliente->nombre }}</option>
                        @endforeach
                    </select>
                    @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="servicio_id">Servicio</label>
                    <select class="select" name="servicio_id" id="servicio_id" required>
                        <option value="">— Selecciona un cliente primero —</option>
                        @foreach ($clientes as $cliente)
                            @foreach ($cliente->servicios as $servicio)
                                <option value="{{ $servicio->id }}" data-cliente="{{ $cliente->id }}" @selected((int) old('servicio_id') === $servicio->id)>
                                    {{ $servicio->nombre }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('servicio_id')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="nombre">Nombre de la campaña</label>
                    <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required>
                    @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="plataforma">Plataforma</label>
                    <select class="select" name="plataforma" id="plataforma" required>
                        @foreach (['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'tiktok_ads' => 'TikTok Ads'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('plataforma') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('plataforma')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="objetivo">Objetivo</label>
                    <select class="select" name="objetivo" id="objetivo" required>
                        @foreach (['leads' => 'Leads', 'ventas' => 'Ventas', 'trafico' => 'Tráfico', 'branding' => 'Branding / Reconocimiento'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('objetivo') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('objetivo')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="presupuesto_mensual">Presupuesto mensual (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="presupuesto_mensual" id="presupuesto_mensual" value="{{ old('presupuesto_mensual', '0') }}" required>
                    @error('presupuesto_mensual')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Audiencia objetivo</h3>
            <div class="field">
                <label class="field__label" for="publico_objetivo">Público objetivo (descripción)</label>
                <textarea class="textarea" name="publico_objetivo" id="publico_objetivo">{{ old('publico_objetivo') }}</textarea>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="rango_edad">Rango de edad</label>
                    <input class="input" type="text" name="rango_edad" id="rango_edad" value="{{ old('rango_edad') }}" placeholder="25-45">
                </div>
                <div class="field">
                    <label class="field__label" for="genero">Género</label>
                    <input class="input" type="text" name="genero" id="genero" value="{{ old('genero') }}" placeholder="Todos / Hombres / Mujeres">
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="ubicacion_geografica">Ubicación geográfica objetivo</label>
                    <input class="input" type="text" name="ubicacion_geografica" id="ubicacion_geografica" value="{{ old('ubicacion_geografica') }}" placeholder="Aguascalientes, México">
                </div>
                <div class="field">
                    <label class="field__label" for="producto_servicio">Producto o servicio a promocionar</label>
                    <input class="input" type="text" name="producto_servicio" id="producto_servicio" value="{{ old('producto_servicio') }}">
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="intereses">Intereses y comportamientos</label>
                <textarea class="textarea" name="intereses" id="intereses">{{ old('intereses') }}</textarea>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Estrategia</h3>
            <div class="field">
                <label class="field__label" for="propuesta_valor">Propuesta de valor del anuncio</label>
                <textarea class="textarea" name="propuesta_valor" id="propuesta_valor">{{ old('propuesta_valor') }}</textarea>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="analisis_competencia">Análisis de competencia en ads</label>
                <textarea class="textarea" name="analisis_competencia" id="analisis_competencia">{{ old('analisis_competencia') }}</textarea>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="url_destino">URL de destino (landing page)</label>
                    <input class="input" type="text" name="url_destino" id="url_destino" value="{{ old('url_destino') }}" placeholder="https://...">
                </div>
                <div class="field">
                    <label class="field__label" for="fecha_inicio_estimada">Fecha de inicio estimada</label>
                    <input class="input" type="date" name="fecha_inicio_estimada" id="fecha_inicio_estimada" value="{{ old('fecha_inicio_estimada') }}">
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="notas">Notas de estrategia</label>
                <textarea class="textarea" name="notas" id="notas">{{ old('notas') }}</textarea>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Checklist de briefing</h3>
            <p class="field__hint" style="margin-bottom: var(--space-3);">No es obligatorio completarlo para crear la campaña — puedes marcarlo después, antes de aprobar la fase.</p>
            <div class="checkbox-group">
                @foreach ($checklistBriefing as $key => $label)
                    <label class="checkbox-item">
                        <input type="checkbox" name="checklist[{{ $key }}]" value="1" @checked(old('checklist.'.$key))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Campaña</button>
                <a href="{{ route('admin.ads.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/ads.js')
@endsection
