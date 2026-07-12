@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Generar Propuesta</h1>
            <p class="page-header__subtitle">Se genera un PDF con los servicios propuestos al cliente</p>
        </div>
        <a href="{{ route('admin.archivos.index') }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Archivos
        </a>
    </div>

    <div class="card card--padded form-card">
        <form method="POST" action="{{ route('admin.archivos.propuestas.preview') }}">
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
                <label class="field__label">Servicios a proponer</label>
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

            <div class="field" style="margin-top: var(--space-4); max-width: 200px;">
                <label class="field__label" for="validez_dias">Vigencia (días)</label>
                <input class="input" type="number" min="1" max="365" name="validez_dias" id="validez_dias" value="{{ old('validez_dias', 15) }}" required>
                @error('validez_dias')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="condiciones">Alcance y condiciones</label>
                <textarea class="textarea" name="condiciones" id="condiciones" style="min-height:160px;" required>{{ old('condiciones', "Esta propuesta incluye la implementación y gestión mensual de los servicios listados arriba.\nLa inversión mensual no incluye el presupuesto publicitario de plataformas (Google Ads, Meta Ads, etc.), que se factura por separado.\nAl aceptar esta propuesta se procederá a la firma del contrato de prestación de servicios correspondiente.") }}</textarea>
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
