@extends('layouts.guest')

@section('title', 'Recuperar contraseña · AgencyOS Admin')

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
            <h1 class="auth-card__title">Recuperar contraseña</h1>
            <p class="auth-card__subtitle">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>
        </div>

        @if (session('status'))
            <div class="auth-status">
                <i class="fa-solid fa-circle-check" style="margin-top:2px"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form class="auth-form" method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="field">
                <label class="field__label" for="email">Correo electrónico</label>
                <input class="input" type="email" name="email" id="email" value="{{ old('email') }}"
                    required autofocus>
                @error('email')
                    <span class="field__error">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn--primary">
                <i class="fa-solid fa-paper-plane"></i> Enviar enlace de recuperación
            </button>
        </form>

        <div class="auth-card__footer">
            <a class="auth-link" href="{{ route('login') }}">Volver a iniciar sesión</a>
        </div>
    </div>
@endsection
