@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/calendario.css')
@endsection

@section('scripts')
    @vite('resources/js/calendario.js')
@endsection

{{--
    Calendario: cuadrícula mensual + panel del día. Los eventos manuales se
    crean, editan y borran aquí; reuniones, entregas, pagos y campañas vienen de su módulo
    (solo lectura, con enlace). El mes inicial viaja embebido; al navegar, el JS pide
    el rango de 42 días a la ruta `eventos`.
--}}
@section('content')
    @include('admin.calendario._calendario')
@endsection
