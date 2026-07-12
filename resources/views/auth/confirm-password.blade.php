@extends('layouts.guest')

@section('title', 'Confirma tu contraseña · AgencyOS Admin')

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
            <h1 class="auth-card__title">Área protegida</h1>
            <p class="auth-card__subtitle">Esta es una zona segura. Confirma tu contraseña antes de continuar.</p>
        </div>

        <form class="auth-form" method="POST" action="{{ route('password.confirm') }}">
            @csrf

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

            <button type="submit" class="btn btn--primary">
                <i class="fa-solid fa-lock"></i> Confirmar
            </button>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/auth.js')
@endsection
