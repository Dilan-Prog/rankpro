@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/desarrollo.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nuevo Proyecto</h1>
            <p class="page-header__subtitle">Fase 1 · Planeación — todo proyecto arranca aquí</p>
        </div>
        <a href="{{ route('admin.desarrollo.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Desarrollo
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.desarrollo.store') }}">
            @csrf

            <h3 class="fase-form__section-title">Datos generales</h3>
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="nombre">Nombre del proyecto</label>
                    <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required>
                    @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="cliente_id">Cliente asignado</label>
                    <select class="select" name="cliente_id" id="cliente_id" required>
                        <option value="">— Selecciona un cliente —</option>
                        @foreach ($clientes as $id => $nombre)
                            <option value="{{ $id }}" @selected((int) old('cliente_id') === $id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4); max-width: 320px;">
                <label class="field__label" for="tipo">Tipo de proyecto</label>
                <select class="select" name="tipo" id="tipo" required>
                    @foreach (['web_nueva' => 'Sitio Web Nuevo', 'rediseno' => 'Rediseño', 'software' => 'Software a Medida', 'landing' => 'Landing Page'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('tipo') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('tipo')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="descripcion">Descripción general / briefing</label>
                <textarea class="textarea" name="descripcion" id="descripcion" placeholder="Contexto del proyecto, alcance a grandes rasgos...">{{ old('descripcion') }}</textarea>
                @error('descripcion')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="objetivos">Objetivos del proyecto</label>
                <textarea class="textarea" name="objetivos" id="objetivos">{{ old('objetivos') }}</textarea>
                @error('objetivos')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="requerimientos_funcionales">Requerimientos funcionales</label>
                    <textarea class="textarea" name="requerimientos_funcionales" id="requerimientos_funcionales">{{ old('requerimientos_funcionales') }}</textarea>
                    @error('requerimientos_funcionales')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="requerimientos_tecnicos">Requerimientos técnicos</label>
                    <textarea class="textarea" name="requerimientos_tecnicos" id="requerimientos_tecnicos">{{ old('requerimientos_tecnicos') }}</textarea>
                    @error('requerimientos_tecnicos')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Presupuesto y responsable</h3>
            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="presupuesto">Presupuesto acordado (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="presupuesto" id="presupuesto" value="{{ old('presupuesto', '0') }}" required>
                    @error('presupuesto')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="anticipo">Anticipo recibido (MXN)</label>
                    <input class="input" type="number" step="0.01" min="0" name="anticipo" id="anticipo" value="{{ old('anticipo', '0') }}">
                    @error('anticipo')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="forma_pago">Forma de pago</label>
                    <select class="select" name="forma_pago" id="forma_pago">
                        <option value="">— Sin definir —</option>
                        @foreach (['mensual' => 'Mensual', 'etapas' => 'Por Etapas', 'unico' => 'Pago Único'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('forma_pago') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('forma_pago')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="responsable">Responsable interno</label>
                    <input class="input" type="text" name="responsable" id="responsable" value="{{ old('responsable') }}" placeholder="Nombre de quien lidera el proyecto">
                    @error('responsable')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
                    <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', now()->format('Y-m-d')) }}">
                    @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="fecha_entrega_estimada">Fecha de entrega estimada</label>
                    <input class="input" type="date" name="fecha_entrega_estimada" id="fecha_entrega_estimada" value="{{ old('fecha_entrega_estimada') }}">
                    @error('fecha_entrega_estimada')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Checklist de planeación</h3>
            <p class="field__hint" style="margin-bottom: var(--space-3);">No es obligatorio completarlo para crear el proyecto — puedes marcarlo después, antes de aprobar la fase.</p>
            <div class="checkbox-group">
                @foreach ($checklistPlaneacion as $key => $label)
                    <label class="checkbox-item">
                        <input type="checkbox" name="checklist[{{ $key }}]" value="1" @checked(old('checklist.'.$key))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Proyecto</button>
                <a href="{{ route('admin.desarrollo.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
