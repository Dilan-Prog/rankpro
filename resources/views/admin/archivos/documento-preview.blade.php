{{--
    Shared preview shell for both DocumentosController::previewContrato and
    previewPropuesta. $documentoHtml is the rendered pdf.contrato/pdf.propuesta
    view (same template DomPDF will use), shown isolated in an iframe so its
    print-oriented CSS doesn't clash with the admin panel's own styles.
    $hidden carries the already-validated form data forward so "Descargar PDF"
    can resubmit it straight to the real store endpoint.
--}}
@extends('layouts.admin')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">{{ $pageTitle }}</h1>
            <p class="page-header__subtitle">Revisa el documento antes de generarlo</p>
        </div>
        <a href="{{ $cancelRoute }}" class="btn btn--secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a editar
        </a>
    </div>

    <div class="card" style="overflow:hidden; margin-bottom: var(--space-6); padding:0;">
        <iframe srcdoc="{{ $documentoHtml }}" style="width:100%; height:75vh; border:0; display:block; background:#fff;" title="Vista previa del documento"></iframe>
    </div>

    <div class="card card--padded">
        <form method="POST" action="{{ $formAction }}">
            @csrf
            <input type="hidden" name="cliente_id" value="{{ $hidden['cliente_id'] }}">
            @foreach ($hidden['servicios'] as $servicioId)
                <input type="hidden" name="servicios[]" value="{{ $servicioId }}">
            @endforeach
            @if (array_key_exists('fecha_inicio', $hidden))
                <input type="hidden" name="fecha_inicio" value="{{ $hidden['fecha_inicio'] }}">
                <input type="hidden" name="fecha_fin" value="{{ $hidden['fecha_fin'] ?? '' }}">
            @endif
            @if (array_key_exists('validez_dias', $hidden))
                <input type="hidden" name="validez_dias" value="{{ $hidden['validez_dias'] }}">
            @endif
            <input type="hidden" name="condiciones" value="{{ $hidden['condiciones'] }}">

            <p style="font-size:var(--text-sm); color:var(--color-muted-foreground); margin-bottom:var(--space-4);">
                ¿Todo se ve correcto? Descarga el PDF final o vuelve a editar los datos.
            </p>
            <div class="form-actions" style="margin-top:0; padding-top:0; border-top:0;">
                <button type="submit" class="btn btn--primary"><i class="fa-solid fa-download"></i> Descargar PDF</button>
                <a href="{{ $cancelRoute }}" class="btn btn--secondary">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
