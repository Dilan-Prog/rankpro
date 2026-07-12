@extends('layouts.guest')

@section('title', 'Iniciar sesión · AgencyOS Admin')

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
            <h1 class="auth-card__title">Iniciar sesión</h1>
            <p class="auth-card__subtitle">Accede al panel de administración de la agencia.</p>
        </div>

        @if (session('status'))
            <div class="auth-status">
                <i class="fa-solid fa-circle-check" style="margin-top:2px"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form class="auth-form" method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label class="field__label" for="email">Correo electrónico</label>
                <input class="input" type="email" name="email" id="email" value="{{ old('email') }}"
                    required autofocus autocomplete="username">
                @error('email')
                    <span class="field__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="password">Contraseña</label>
                <div class="password-field">
                    <input class="input" type="password" name="password" id="password" required
                        autocomplete="current-password">
                    <button type="button" class="password-field__toggle" data-password-toggle="password" aria-label="Mostrar contraseña">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                @error('password')
                    <span class="field__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form__row">
                <label class="auth-checkbox">
                    <input type="checkbox" name="remember">
                    Recordarme
                </label>
                @if (Route::has('password.request'))
                    <a class="auth-link" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                @endif
            </div>

            <button type="submit" class="btn btn--primary">
                <i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión
            </button>
        </form>

        @if (Route::has('register'))
            <div class="auth-card__footer">
                ¿No tienes cuenta? <a class="auth-link" href="{{ route('register') }}">Regístrate</a>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    @vite('resources/js/auth.js')
@endsection
