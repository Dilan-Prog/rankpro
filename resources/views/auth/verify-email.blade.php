@extends('layouts.guest')

@section('title', 'Verifica tu correo · AgencyOS Admin')

@section('styles')
    @vite('resources/css/admin/auth.css')
@endsection

@section('content')
    <div class="auth-card">
        <div class="auth-card__brand">
            <span class="auth-card__logo"><i class="fa-solid fa-rocket"></i></span>
            <span class="auth-card__brand-name">AgencyOS</span>
        </div>

        <div class="auth-card__header">
            <h1 class="auth-card__title">Verifica tu correo</h1>
            <p class="auth-card__subtitle">
                Gracias por registrarte. Antes de continuar, confirma tu correo dando clic en el enlace que te enviamos.
                Si no lo recibiste, con gusto te enviamos otro.
            </p>
        </div>

        @if (session('status') === 'verification-link-sent')
            <div class="auth-status">
                <i class="fa-solid fa-circle-check" style="margin-top:2px"></i>
                <span>Se envió un nuevo enlace de verificación al correo que registraste.</span>
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn--primary" style="width:100%; justify-content:center;">
                <i class="fa-solid fa-paper-plane"></i> Reenviar correo de verificación
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top: var(--space-3);">
            @csrf
            <button type="submit" class="btn btn--secondary" style="width:100%; justify-content:center;">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </button>
        </form>
    </div>
@endsection
