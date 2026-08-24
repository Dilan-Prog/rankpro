@extends('layouts.guest')

@section('title', 'Iniciar sesión · AgencyOS Admin')

@section('bodyClass', 'auth-split-page')

@section('styles')
    @vite('resources/css/admin/auth.css')
@endsection

@section('content')
    <div class="auth-split">
        <div class="auth-split__form-side">
            <div class="auth-split__form-inner">
                <a href="{{ route('home') }}" class="auth-split__back">
                    <i class="fa-solid fa-chevron-left"></i> Volver al inicio
                </a>

                <img src="{{ asset('images/rankpro-logo-black.png') }}" alt="RankPro" class="auth-split__logo">

                <div class="auth-card__header" style="text-align:left; margin-top: var(--space-6);">
                    <h1 class="auth-card__title">Inicia sesión en tu cuenta</h1>
                    <p class="auth-card__subtitle">Bienvenido de vuelta. Gestiona tus campañas.</p>
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
                        <div class="password-field">
                            <i class="fa-solid fa-at auth-form__field-icon"></i>
                            <input class="input" style="padding-left: 38px;" type="email" name="email" id="email" value="{{ old('email') }}"
                                placeholder="tu@empresa.com" required autofocus autocomplete="username">
                        </div>
                        @error('email')
                            <span class="field__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="password">Contraseña</label>
                        <div class="password-field">
                            <i class="fa-solid fa-lock auth-form__field-icon"></i>
                            <input class="input" style="padding-left: 38px;" type="password" name="password" id="password"
                                placeholder="••••••••" required autocomplete="current-password">
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
        </div>

        <div class="auth-split__hero-side">
            <div class="auth-split__hero-panel">
                <img src="{{ asset('images/rankpro-logo-white.png') }}" alt="RankPro" class="auth-split__hero-logo">
                <h2 class="auth-split__hero-title">Gestiona todas tus campañas desde un solo lugar</h2>
                <p class="auth-split__hero-desc">SEO, Ads, redes sociales y analíticas centralizadas. Reportes automáticos en tiempo real.</p>

                <div class="auth-split__stats">
                    <div class="auth-split__stat">
                        <div class="auth-split__stat-value">+200</div>
                        <div class="auth-split__stat-label">Clientes satisfechos</div>
                    </div>
                    <div class="auth-split__stat">
                        <div class="auth-split__stat-value">4 Años</div>
                        <div class="auth-split__stat-label">De experiencia en México</div>
                    </div>
                    <div class="auth-split__stat">
                        <div class="auth-split__stat-value">98%</div>
                        <div class="auth-split__stat-label">Tasa de retención</div>
                    </div>
                    <div class="auth-split__stat">
                        <div class="auth-split__stat-value">$120M+</div>
                        <div class="auth-split__stat-label">En ventas generadas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/auth.js')
@endsection
