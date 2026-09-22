@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/configuracion.css')
@endsection

{{--
    Configuración de correo (SMTP): una fila única que, activada, sobreescribe
    los MAIL_* del .env (ver App\Support\ConfiguracionSmtpAplicador). Formulario
    clásico (POST + @method PUT, errores de sesión) porque es una sola pantalla
    sencilla — no necesita el patrón fetch/JSON de los módulos más grandes.
    "Probar" sí es AJAX (resources/js/configuracion.js): manda lo que hay
    escrito en el formulario ahora mismo, aunque no se haya guardado todavía.
--}}
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Configuración de correo</h1>
            <p class="page-header__subtitle">SMTP que usa el panel para mandar correos (módulo Correo, notificaciones). Sin activar, se usa el del <code>.env</code> del servidor.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="form-status"><i class="fa-solid fa-circle-check" style="margin-top:2px"></i><span>{{ session('status') }}</span></div>
    @endif

    <div class="card card--padded cfg-smtp" data-configuracion-smtp data-prueba-url="{{ route('admin.configuracion.smtp.probar') }}">
        <form method="POST" action="{{ route('admin.configuracion.smtp.update') }}" id="smtpForm">
            @csrf
            @method('PUT')

            <label class="cfg-switch" style="margin-bottom: var(--space-4);">
                <input type="checkbox" name="activa" value="1" @checked(old('activa', $config?->activa))>
                <span class="cfg-switch__pista"></span>
                <span class="cfg-switch__texto">Usar esta configuración</span>
                <span class="field__hint">Si está apagado, el panel sigue mandando correos con el <code>.env</code> del servidor (host actual: <code class="u-mono">{{ $envActivo['host'] ?: '—' }}:{{ $envActivo['port'] ?: '—' }}</code>).</span>
            </label>

            <div class="form-grid form-grid--2">
                <div class="field">
                    <label class="field__label" for="cfg_host">Servidor SMTP <span style="color:var(--text-danger)">*</span></label>
                    <input class="input u-mono" type="text" name="host" id="cfg_host" maxlength="255" placeholder="smtp.hostinger.com" value="{{ old('host', $config?->host) }}" required>
                    @error('host')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="cfg_puerto">Puerto <span style="color:var(--text-danger)">*</span></label>
                    <input class="input u-mono" type="number" name="puerto" id="cfg_puerto" min="1" max="65535" placeholder="465" value="{{ old('puerto', $config?->puerto) }}" required>
                    @error('puerto')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="cfg_cifrado">Cifrado</label>
                    <select class="select" name="cifrado" id="cfg_cifrado">
                        <option value="" @selected(old('cifrado', $config?->cifrado) === null)>Ninguno</option>
                        <option value="ssl" @selected(old('cifrado', $config?->cifrado) === 'ssl')>SSL (puerto 465 en Hostinger)</option>
                        <option value="tls" @selected(old('cifrado', $config?->cifrado) === 'tls')>TLS (puerto 587 en Hostinger)</option>
                    </select>
                    @error('cifrado')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="cfg_usuario">Usuario</label>
                    <input class="input u-mono" type="text" name="usuario" id="cfg_usuario" maxlength="255" placeholder="hola@rankprosolutions.com.mx" value="{{ old('usuario', $config?->usuario) }}">
                    @error('usuario')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field" style="margin-top: var(--space-4);">
                <label class="field__label" for="cfg_password">Contraseña</label>
                @if ($tieneContrasena)
                    <div class="cfg-smtp__password" data-cfg-password-bloqueada>
                        <input class="input u-mono" type="password" value="••••••••••••" disabled>
                        <button type="button" class="btn btn--secondary btn--sm" data-cfg-password-cambiar>Cambiar</button>
                    </div>
                @endif
                <input class="input u-mono" type="password" name="password" id="cfg_password" maxlength="255" autocomplete="new-password"
                       placeholder="{{ $tieneContrasena ? '(sin cambios)' : '' }}" {{ $tieneContrasena ? 'hidden' : '' }} data-cfg-password-campo>
                <p class="field__hint">{{ $tieneContrasena ? 'Se guarda cifrada; deja "Cambiar" sin tocar para conservar la actual.' : 'Se guarda cifrada en la base de datos, nunca en texto plano.' }}</p>
                @error('password')<span class="field__error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid form-grid--2" style="margin-top: var(--space-4);">
                <div class="field">
                    <label class="field__label" for="cfg_remitente_email">Correo remitente</label>
                    <input class="input u-mono" type="email" name="remitente_email" id="cfg_remitente_email" maxlength="255" placeholder="hola@rankprosolutions.com.mx" value="{{ old('remitente_email', $config?->remitente_email) }}">
                    @error('remitente_email')<span class="field__error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field__label" for="cfg_remitente_nombre">Nombre remitente</label>
                    <input class="input" type="text" name="remitente_nombre" id="cfg_remitente_nombre" maxlength="255" placeholder="RankPro Solutions" value="{{ old('remitente_nombre', $config?->remitente_nombre) }}">
                    @error('remitente_nombre')<span class="field__error">{{ $message }}</span>@enderror
                </div>
            </div>

            <p class="field__hint" style="margin-top: var(--space-2);">
                <i class="fa-solid fa-shield-halved"></i>
                El correo remitente debe ser del mismo dominio que autentica el SMTP; con otro dominio, Gmail/Outlook puede marcarlo como suplantación.
            </p>

            <div class="cfg-smtp__acciones">
                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                </button>
            </div>
        </form>

        <div class="cfg-smtp__prueba">
            <h3 class="card__header-title">Probar esta configuración</h3>
            <p class="field__hint">Manda un correo con lo que hay escrito arriba ahora mismo, aunque no lo hayas guardado todavía.</p>
            <div class="cfg-smtp__prueba-fila">
                <input class="input u-mono" type="email" placeholder="tu@correo.com" data-cfg-prueba-email>
                <button type="button" class="btn btn--secondary" data-cfg-prueba-enviar>
                    <i class="fa-solid fa-flask"></i> Enviar prueba
                </button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/configuracion.js')
@endsection
