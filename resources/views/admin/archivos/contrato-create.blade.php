@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Generar Contrato</h1>
            <p class="page-header__subtitle">Se genera un PDF con los datos del cliente y sus servicios</p>
        </div>
        <a href="{{ route('admin.archivos.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Archivos
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.archivos.contratos.preview') }}">
            @csrf

            <div class="field">
                <label class="field__label" for="cliente_id">Cliente</label>
                <select class="select" name="cliente_id" id="documentoClienteSelect" required>
                    <option value="">— Selecciona un cliente —</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected((int) old('cliente_id') === $cliente->id)>{{ $cliente->empresa ?: $cliente->nombre }}</option>
                    @endforeach
                </select>
                @error('cliente_id')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label">Servicios a incluir</label>
                @foreach ($clientes as $cliente)
                    <div class="checkbox-group documento-servicios-group" data-cliente-group="{{ $cliente->id }}" hidden>
                        @forelse ($cliente->servicios as $servicio)
                            <label class="checkbox-item">
                                <input type="checkbox" name="servicios[]" value="{{ $servicio->id }}"
                                    @checked(in_array($servicio->id, old('servicios', [])))>
                                {{ $servicio->nombre }} — ${{ number_format($servicio->precio_mensual) }}/mes
                            </label>
                        @empty
                            <p class="field__hint">Este cliente no tiene servicios registrados todavía.</p>
                        @endforelse
                    </div>
                @endforeach
                <p class="field__hint" id="documentoServiciosHint">Selecciona un cliente para ver sus servicios.</p>
                @error('servicios')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="fecha_inicio">Fecha de inicio</label>
                    <input class="input" type="date" name="fecha_inicio" id="fecha_inicio" value="{{ old('fecha_inicio', now()->format('Y-m-d')) }}" required>
                    @error('fecha_inicio')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="fecha_fin">Fecha de fin (opcional)</label>
                    <input class="input" type="date" name="fecha_fin" id="fecha_fin" value="{{ old('fecha_fin') }}">
                    @error('fecha_fin')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="condiciones">Condiciones generales</label>
                <textarea class="textarea" name="condiciones" id="condiciones" style="min-height:160px;" required>{{ old('condiciones', "1. El presente contrato tiene vigencia mensual con renovación automática salvo cancelación por escrito con 30 días de anticipación.\n2. Los pagos se realizarán conforme a la forma y método de pago registrados en el expediente del cliente.\n3. RankPro Solutions se compromete a entregar reportes mensuales de resultados.\n4. Cualquier servicio adicional no listado en este contrato será cotizado por separado.") }}</textarea>
                @error('condiciones')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-eye"></i> Vista previa</button>
                <a href="{{ route('admin.archivos.index') }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/documentos.js')
@endsection
