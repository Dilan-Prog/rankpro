@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/integraciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">API y tokens</h1>
            <p class="page-header__subtitle">Tokens de acceso para conectar RankPro con n8n u otras herramientas.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.integraciones.index') }}" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> Integraciones</a>
            <a href="{{ route('admin.integraciones.api.docs') }}" class="btn btn--ghost"><i class="fa-solid fa-book"></i> Documentación</a>
            <button type="button" class="btn btn--primary" id="abrirTokenModal"><i class="fa-solid fa-plus"></i> Nuevo token</button>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <p style="color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;">
            Cada token da acceso a <code>/api/v1/*</code> con las habilidades que elijas
            (leer y/o escribir, por módulo). El token se muestra <strong>una sola vez</strong>
            al crearlo — guárdalo en la credencial de n8n antes de cerrar el diálogo.
            Base URL: <code>{{ url('/api/v1') }}</code>. Cabecera: <code>Authorization: Bearer &lt;token&gt;</code>.
        </p>
    </div>

    <x-data-table :headers="['Nombre', 'Habilidades', 'Propietario', 'Último uso', 'Caduca', '']">
        @forelse ($tokens as $t)
            <tr data-token-row="{{ $t['id'] }}">
                <td>{{ $t['nombre'] }}</td>
                <td>
                    @foreach ($t['habilidades'] as $h)
                        <span class="badge badge--neutral" style="margin: 2px;">{{ $h }}</span>
                    @endforeach
                </td>
                <td>{{ $t['propietario'] }}</td>
                <td>{{ $t['ultimo_uso'] ?? 'Nunca' }}</td>
                <td>{{ $t['expira_en'] ?? 'Sin caducidad' }}</td>
                <td>
                    <button type="button" class="btn btn--icon btn--danger" data-revocar-token="{{ $t['id'] }}" title="Revocar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr class="table__empty"><td colspan="6">Aún no hay tokens. Crea el primero para conectar n8n.</td></tr>
        @endforelse
    </x-data-table>

    <x-modal id="tokenModal">
        <x-slot:header><h2>Nuevo token de API</h2></x-slot:header>
        <form id="tokenForm">
            <div class="field">
                <label class="field__label">Nombre</label>
                <input type="text" name="nombre" class="input" placeholder="n8n - flujo de reportes" required maxlength="80">
            </div>
            <div class="field">
                <label class="field__label">Caducidad (opcional)</label>
                <input type="datetime-local" name="expira_en" class="input">
            </div>
            <div class="field">
                <label class="field__label">Habilidades</label>
                <label style="display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem;">
                    <input type="checkbox" name="todo" value="*"> <strong>Acceso total (todos los módulos, leer y escribir)</strong>
                </label>
                <div class="table-wrap" style="max-height: 320px; overflow-y: auto;">
                    <table class="table">
                        <thead><tr><th>Módulo</th><th>Leer</th><th>Escribir</th></tr></thead>
                        <tbody>
                            @foreach ($modulos as $m)
                                <tr>
                                    <td>{{ $etiquetas[$m] }}</td>
                                    <td><input type="checkbox" name="habilidades[]" value="{{ $m }}:leer" data-modulo="{{ $m }}"></td>
                                    <td><input type="checkbox" name="habilidades[]" value="{{ $m }}:escribir" data-modulo="{{ $m }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="tokenModal">Cancelar</button>
                <button type="submit" class="btn btn--primary">Crear token</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="tokenCreadoModal">
        <x-slot:header><h2>Token creado</h2></x-slot:header>
        <p style="color: var(--text-secondary); margin-bottom: .75rem;">
            Cópialo ahora: no se puede volver a mostrar. Si lo pierdes, tendrás que crear uno nuevo.
        </p>
        <div style="display:flex; gap:.5rem;">
            <input type="text" id="tokenCreadoValor" class="input" readonly>
            <button type="button" class="btn btn--primary" id="tokenCreadoCopiar">Copiar</button>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn--secondary" data-modal-close="tokenCreadoModal">Cerrar</button>
        </div>
    </x-modal>

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').content;
            var toast = window.AgencyOS.toast;
            var openModal = window.AgencyOS.openModal;
            var closeModal = window.AgencyOS.closeModal;

            document.getElementById('abrirTokenModal').addEventListener('click', function () { openModal('tokenModal'); });

            document.getElementById('tokenForm').addEventListener('submit', function (e) {
                e.preventDefault();
                var form = e.target;
                var todo = form.querySelector('[name="todo"]').checked;
                var habilidades = todo ? ['*'] : Array.from(form.querySelectorAll('[name="habilidades[]"]:checked')).map(function (i) { return i.value; });

                if (!habilidades.length) {
                    toast('Elige al menos una habilidad.', 'error');
                    return;
                }

                fetch('{{ route('admin.integraciones.api.tokens.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ nombre: form.nombre.value, habilidades: habilidades, expira_en: form.expira_en.value || null }),
                }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                    .then(function (res) {
                        if (!res.ok) { toast(Object.values(res.data.errors || {})[0]?.[0] || 'No se pudo crear el token.', 'error'); return; }
                        closeModal('tokenModal');
                        form.reset();
                        document.getElementById('tokenCreadoValor').value = res.data.token;
                        openModal('tokenCreadoModal');
                        toast('Token creado. Cópialo ahora.', 'success');
                        setTimeout(function () { window.location.reload(); }, 50);
                    });
            });

            document.getElementById('tokenCreadoCopiar').addEventListener('click', function () {
                var input = document.getElementById('tokenCreadoValor');
                input.select();
                navigator.clipboard.writeText(input.value).then(function () { toast('Copiado.', 'success'); });
            });

            document.querySelectorAll('[data-revocar-token]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!window.confirm('¿Revocar este token? Dejará de funcionar de inmediato.')) return;
                    var id = btn.getAttribute('data-revocar-token');
                    fetch('{{ url('admin/integraciones/api/tokens') }}/' + id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    }).then(function (r) {
                        if (!r.ok) { toast('No se pudo revocar.', 'error'); return; }
                        document.querySelector('[data-token-row="' + id + '"]').remove();
                        toast('Token revocado.', 'success');
                    });
                });
            });
        })();
    </script>
@endsection
