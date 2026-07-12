@extends('layouts.guest')

@section('title', 'Restablecer contraseña · AgencyOS Admin')

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
            <h1 class="auth-card__title">Restablecer contraseña</h1>
            <p class="auth-card__subtitle">Elige una nueva contraseña para tu cuenta.</p>
        </div>

        <form class="auth-form" method="POST" action="{{ route('password.store') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="field">
                <label class="field__label" for="email">Correo electrónico</label>
                <input class="input" type="email" name="email" id="email" value="{{ old('email', $request->email) }}"
                    required autofocus autocomplete="username">
                @error('email')
                    <span class="field__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="password">Nueva contraseña</label>
                <div class="password-field">
                    <input class="input" type="password" name="password" id="password" required
                        autocomplete="new-password">
                    <button type="button" class="password-field__toggle" data-password-toggle="password" aria-label="Mostrar contraseña">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                @error('password')
                    <span class="field__error">{{ $message }}</span>
                @enderror
            </div>

            <div class="field">
                <label class="field__label" for="password_confirmation">Confirmar contraseña</label>
                <div class="password-field">
                    <input class="input" type="password" name="password_confirmation" id="password_confirmation"
                        required autocomplete="new-password">
                    <button type="button" class="password-field__toggle" data-password-toggle="password_confirmation" aria-label="Mostrar contraseña">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                @error('password_confirmation')
                    <span class="field__error">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn--primary">
                <i class="fa-solid fa-key"></i> Restablecer contraseña
            </button>
        </form>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/auth.js')
@endsection
