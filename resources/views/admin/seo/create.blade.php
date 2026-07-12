@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/seo.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nueva Campaña SEO</h1>
            <p class="page-header__subtitle">Fase 1 · Auditoría — toda campaña arranca aquí</p>
        </div>
        <a href="{{ route('admin.seo.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a SEO
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.seo.store') }}">
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
                            @foreach ($cliente->servicios->where('tipo', 'seo') as $servicio)
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
                    <label class="field__label" for="url_sitio">URL del sitio</label>
                    <input class="input" type="text" name="url_sitio" id="url_sitio" value="{{ old('url_sitio') }}" placeholder="https://...">
                    @error('url_sitio')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4); max-width: 240px;">
                <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
                <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', now()->format('Y-m-d')) }}">
                @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">SEO Score y velocidad</h3>
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="seo_score">SEO Score inicial (0-100)</label>
                    <input class="input" type="number" min="0" max="100" name="seo_score" id="seo_score" value="{{ old('seo_score') }}">
                </div>
                <div class="field">
                    <label class="field__label" for="herramienta">Herramienta usada</label>
                    <select class="select" name="herramienta" id="herramienta">
                        <option value="">— Sin definir —</option>
                        <option value="semrush">Semrush</option>
                        <option value="ahrefs">Ahrefs</option>
                        <option value="screaming_frog">Screaming Frog</option>
                        <option value="google_search_console">Google Search Console</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="velocidad_mobile">Velocidad Mobile (0-100)</label>
                    <input class="input" type="number" step="0.01" min="0" max="100" name="velocidad_mobile" id="velocidad_mobile" value="{{ old('velocidad_mobile') }}">
                </div>
                <div class="field">
                    <label class="field__label" for="velocidad_desktop">Velocidad Desktop (0-100)</label>
                    <input class="input" type="number" step="0.01" min="0" max="100" name="velocidad_desktop" id="velocidad_desktop" value="{{ old('velocidad_desktop') }}">
                </div>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Core Web Vitals</h3>
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" style="font-weight:600;">Mobile</label>
                </div>
                <div class="field">
                    <label class="field__label" style="font-weight:600;">Desktop</label>
                </div>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-2);">
                <div style="display:flex; gap: var(--space-2);">
                    <div class="field"><label class="field__label" for="lcp_mobile">LCP</label><input class="input" type="number" step="0.01" min="0" name="lcp_mobile" id="lcp_mobile" value="{{ old('lcp_mobile') }}"></div>
                    <div class="field"><label class="field__label" for="fid_mobile">FID</label><input class="input" type="number" step="0.01" min="0" name="fid_mobile" id="fid_mobile" value="{{ old('fid_mobile') }}"></div>
                    <div class="field"><label class="field__label" for="cls_mobile">CLS</label><input class="input" type="number" step="0.001" min="0" name="cls_mobile" id="cls_mobile" value="{{ old('cls_mobile') }}"></div>
                </div>
                <div style="display:flex; gap: var(--space-2);">
                    <div class="field"><label class="field__label" for="lcp_desktop">LCP</label><input class="input" type="number" step="0.01" min="0" name="lcp_desktop" id="lcp_desktop" value="{{ old('lcp_desktop') }}"></div>
                    <div class="field"><label class="field__label" for="fid_desktop">FID</label><input class="input" type="number" step="0.01" min="0" name="fid_desktop" id="fid_desktop" value="{{ old('fid_desktop') }}"></div>
                    <div class="field"><label class="field__label" for="cls_desktop">CLS</label><input class="input" type="number" step="0.001" min="0" name="cls_desktop" id="cls_desktop" value="{{ old('cls_desktop') }}"></div>
                </div>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Errores técnicos</h3>
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="errores_tecnicos">Errores técnicos encontrados</label>
                    <input class="input" type="number" min="0" name="errores_tecnicos" id="errores_tecnicos" value="{{ old('errores_tecnicos') }}">
                </div>
                <div class="field">
                    <label class="field__label" for="errores_404">Errores 404 detectados</label>
                    <input class="input" type="number" min="0" name="errores_404" id="errores_404" value="{{ old('errores_404') }}">
                </div>
            </div>
            <div class="field" style="margin-top: var(--space-4); max-width: 320px;">
                <label class="field__label" for="redirecciones_incorrectas">Redirecciones incorrectas</label>
                <input class="input" type="number" min="0" name="redirecciones_incorrectas" id="redirecciones_incorrectas" value="{{ old('redirecciones_incorrectas') }}">
            </div>

            <div class="checkbox-group" style="margin-top: var(--space-4);">
                <label class="checkbox-item">
                    <input type="checkbox" name="indexacion_ok" value="1" @checked(old('indexacion_ok'))>
                    Estado de indexación en Google correcto
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="sitemap_ok" value="1" @checked(old('sitemap_ok'))>
                    Sitemap presente
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="robots_ok" value="1" @checked(old('robots_ok'))>
                    Robots.txt correcto
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="duplicidad_contenido" value="1" @checked(old('duplicidad_contenido'))>
                    Duplicidad de contenido detectada
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="canonical_ok" value="1" @checked(old('canonical_ok'))>
                    Etiquetas canonical presentes
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" name="schema_ok" value="1" @checked(old('schema_ok'))>
                    Schema markup presente
                </label>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="notas">Notas de auditoría</label>
                <textarea class="textarea" name="notas" id="notas">{{ old('notas') }}</textarea>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Checklist de auditoría</h3>
            <p class="field__hint" style="margin-bottom: var(--space-3);">No es obligatorio completarlo para crear la campaña — puedes marcarlo después, antes de aprobar la fase.</p>
            <div class="checkbox-group">
                @foreach ($checklistAuditoria as $key => $label)
                    <label class="checkbox-item">
                        <input type="checkbox" name="checklist[{{ $key }}]" value="1" @checked(old('checklist.'.$key))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Campaña</button>
                <a href="{{ route('admin.seo.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/seo.js')
@endsection
