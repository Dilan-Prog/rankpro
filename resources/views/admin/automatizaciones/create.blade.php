@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/automatizaciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Nuevo Proyecto de Automatización</h1>
            <p class="page-header__subtitle">Fase 1 · Diagnóstico — todo proyecto arranca aquí</p>
        </div>
        <a href="{{ route('admin.automatizaciones.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Automatizaciones
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.automatizaciones.store') }}">
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
                            @foreach ($cliente->servicios->where('tipo', 'automatizacion') as $servicio)
                                <option value="{{ $servicio->id }}" data-cliente="{{ $cliente->id }}" @selected((int) old('servicio_id') === $servicio->id)>
                                    {{ $servicio->nombre }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('servicio_id')<span class="field__error">{{ $message }}</span>@enderror
                    <span class="field__hint">Si el cliente no tiene un servicio de tipo "Automatización" todavía, créalo primero en Servicios.</span>
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="nombre">Nombre del proyecto</label>
                    <input class="input" type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required placeholder="Ej. Automatización de citas — WhatsApp">
                    @error('nombre')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
                    <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', now()->format('Y-m-d')) }}">
                    @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Diagnóstico inicial</h3>
            <div class="field">
                <label class="field__label" for="objetivo_cliente">Objetivo del cliente</label>
                <textarea class="textarea" name="objetivo_cliente" id="objetivo_cliente" placeholder="Qué quiere lograr automatizando este proceso">{{ old('objetivo_cliente') }}</textarea>
            </div>
            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="procesos_actuales">Procesos actuales (manuales)</label>
                <textarea class="textarea" name="procesos_actuales" id="procesos_actuales">{{ old('procesos_actuales') }}</textarea>
            </div>
            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="herramientas_actuales">Herramientas actuales</label>
                    <input class="input" type="text" name="herramientas_actuales" id="herramientas_actuales" value="{{ old('herramientas_actuales') }}" placeholder="Ej. WhatsApp Business, HubSpot">
                </div>
                <div class="field">
                    <label class="field__label" for="volumen_mensual_estimado">Volumen mensual estimado</label>
                    <input class="input" type="number" min="0" name="volumen_mensual_estimado" id="volumen_mensual_estimado" value="{{ old('volumen_mensual_estimado') }}" placeholder="Mensajes o tareas al mes">
                </div>
            </div>

            <div class="checkbox-group" style="margin-top: var(--space-4);">
                <label class="checkbox-item">
                    <input type="checkbox" name="viable" value="1" @checked(old('viable'))>
                    Viabilidad de automatización confirmada
                </label>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="notas">Notas</label>
                <textarea class="textarea" name="notas" id="notas">{{ old('notas') }}</textarea>
            </div>

            <h3 class="fase-form__section-title" style="margin-top: var(--space-6);">Checklist de diagnóstico</h3>
            <p class="field__hint" style="margin-bottom: var(--space-3);">No es obligatorio completarlo para crear el proyecto — puedes marcarlo después, antes de aprobar la fase.</p>
            <div class="checkbox-group">
                @foreach ($checklistDiagnostico as $key => $label)
                    <label class="checkbox-item">
                        <input type="checkbox" name="checklist[{{ $key }}]" value="1" @checked(old('checklist.'.$key))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-check"></i> Crear Proyecto</button>
                <a href="{{ route('admin.automatizaciones.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/automatizaciones.js')
@endsection
