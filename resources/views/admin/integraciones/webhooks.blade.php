@extends('layouts.admin')

@section('styles')
    @vite('resources/css/admin/integraciones.css')
@endsection

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Webhooks</h1>
            <p class="page-header__subtitle">Notifica a n8n u otras herramientas cuando pasa algo en RankPro.</p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('admin.integraciones.index') }}" class="btn btn--ghost"><i class="fa-solid fa-arrow-left"></i> Integraciones</a>
            <button type="button" class="btn btn--primary" id="abrirWebhookModal"><i class="fa-solid fa-plus"></i> Nuevo webhook</button>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <p style="color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;">
            Cada webhook manda un <code>POST</code> a tu URL cuando ocurre alguno de los eventos elegidos,
            con la cabecera <code>X-RankPro-Signature: sha256=&lt;hmac&gt;</code> (HMAC-SHA256 de
            <code>"{timestamp}.{body}"</code> con el secreto del webhook) para que verifiques que el envío
            es legítimo. El secreto se muestra <strong>una sola vez</strong> al crear o regenerar el webhook.
        </p>
    </div>

    <x-data-table :headers="['Nombre', 'URL', 'Eventos', 'Activo', 'Último disparo', '']">
        @forelse ($webhooks as $w)
            <tr data-webhook-row="{{ $w['id'] }}">
                <td>{{ $w['nombre'] }}</td>
                <td style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $w['url'] }}">{{ $w['url'] }}</td>
                <td>
                    @if (in_array('*', $w['eventos'] ?? []))
                        <span class="badge badge--primary" style="margin:2px;">Todos</span>
                    @else
                        @foreach (array_slice($w['eventos'] ?? [], 0, 3) as $ev)
                            <span class="badge badge--neutral" style="margin:2px;">{{ $ev }}</span>
                        @endforeach
                        @if (count($w['eventos'] ?? []) > 3)
                            <span class="badge badge--neutral" style="margin:2px;">+{{ count($w['eventos']) - 3 }}</span>
                        @endif
                    @endif
                </td>
                <td><x-badge :status="$w['activo'] ? 'activo' : 'pausado'" /></td>
                <td>{{ $w['ultimo_disparo_en'] ?? 'Nunca' }}</td>
                <td style="white-space: nowrap;">
                    <button type="button" class="btn btn--icon" data-probar-webhook="{{ $w['id'] }}" title="Probar">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                    <button type="button" class="btn btn--icon" data-ver-entregas="{{ $w['id'] }}" title="Ver entregas">
                        <i class="fa-solid fa-list"></i>
                    </button>
                    <button type="button" class="btn btn--icon" data-editar-webhook="{{ $w['id'] }}"
                        data-nombre="{{ $w['nombre'] }}" data-url="{{ $w['url'] }}"
                        data-eventos="{{ json_encode($w['eventos']) }}" data-activo="{{ $w['activo'] ? 1 : 0 }}"
                        title="Editar">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="btn btn--icon btn--danger" data-eliminar-webhook="{{ $w['id'] }}" title="Eliminar">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr class="table__empty"><td colspan="6">Aún no hay webhooks. Crea el primero para conectar n8n.</td></tr>
        @endforelse
    </x-data-table>

    <x-modal id="webhookModal">
        <x-slot:header><h2 id="webhookModalTitulo">Nuevo webhook</h2></x-slot:header>
        <form id="webhookForm">
            <input type="hidden" name="id" value="">
            <div class="field">
                <label class="field__label">Nombre</label>
                <input type="text" name="nombre" class="input" placeholder="n8n - notificaciones de clientes" required maxlength="120">
            </div>
            <div class="field">
                <label class="field__label">URL</label>
                <input type="url" name="url" class="input" placeholder="https://n8n.midominio.com/webhook/rankpro" required maxlength="500">
            </div>
            <div class="field">
                <label style="display:flex; align-items:center; gap:.5rem;">
                    <input type="checkbox" name="activo" value="1" checked> Activo
                </label>
            </div>
            <div class="field">
                <label class="field__label">Eventos</label>
                <label style="display:flex; align-items:center; gap:.5rem; margin-bottom:.75rem;">
                    <input type="checkbox" name="todo" value="*"> <strong>Todos los eventos</strong>
                </label>
                <div class="table-wrap" style="max-height: 280px; overflow-y: auto;">
                    <table class="table">
                        <thead><tr><th>Evento</th><th>Descripción</th></tr></thead>
                        <tbody>
                            @foreach ($eventos as $e)
                                <tr>
                                    <td><input type="checkbox" name="eventos[]" value="{{ $e['evento'] }}"> {{ $e['evento'] }}</td>
                                    <td style="color: var(--text-secondary); font-size: 0.85rem;">{{ $e['descripcion'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn--secondary" data-modal-close="webhookModal">Cancelar</button>
                <button type="submit" class="btn btn--primary">Guardar</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="webhookSecretoModal">
        <x-slot:header><h2>Secreto del webhook</h2></x-slot:header>
        <p style="color: var(--text-secondary); margin-bottom: .75rem;">
            Cópialo ahora: no se puede volver a mostrar. Úsalo para verificar la firma <code>X-RankPro-Signature</code> en n8n.
        </p>
        <div style="display:flex; gap:.5rem;">
            <input type="text" id="webhookSecretoValor" class="input" readonly>
            <button type="button" class="btn btn--primary" id="webhookSecretoCopiar">Copiar</button>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn--secondary" data-modal-close="webhookSecretoModal">Cerrar</button>
        </div>
    </x-modal>

    <x-modal id="webhookEntregasModal" size="lg">
        <x-slot:header><h2>Entregas</h2></x-slot:header>
        <div id="webhookEntregasBody">
            <p style="color: var(--text-secondary);">Cargando...</p>
        </div>
    </x-modal>

    <script>
        (function () {
            var csrf = document.querySelector('meta[name="csrf-token"]').content;
            var toast = window.AgencyOS.toast;
            var openModal = window.AgencyOS.openModal;
            var closeModal = window.AgencyOS.closeModal;

            var estadoBadgeClass = { pendiente: 'badge--warning', entregado: 'badge--success', fallido: 'badge--danger' };

            function urlWebhook(id) { return '{{ url('admin/integraciones/webhooks') }}/' + id; }

            // ---------- Alta / edición ----------
            var form = document.getElementById('webhookForm');
            var modalTitulo = document.getElementById('webhookModalTitulo');

            document.getElementById('abrirWebhookModal').addEventListener('click', function () {
                form.reset();
                form.id.value = '';
                modalTitulo.textContent = 'Nuevo webhook';
                openModal('webhookModal');
            });

            document.querySelectorAll('[data-editar-webhook]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    form.reset();
                    var eventos = JSON.parse(btn.getAttribute('data-eventos') || '[]');
                    form.id.value = btn.getAttribute('data-editar-webhook');
                    form.nombre.value = btn.getAttribute('data-nombre');
                    form.url.value = btn.getAttribute('data-url');
                    form.activo.checked = btn.getAttribute('data-activo') === '1';
                    if (eventos.indexOf('*') !== -1) {
                        form.todo.checked = true;
                    } else {
                        eventos.forEach(function (ev) {
                            var cb = form.querySelector('[name="eventos[]"][value="' + ev + '"]');
                            if (cb) cb.checked = true;
                        });
                    }
                    modalTitulo.textContent = 'Editar webhook';
                    openModal('webhookModal');
                });
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var todo = form.todo.checked;
                var eventos = todo ? ['*'] : Array.from(form.querySelectorAll('[name="eventos[]"]:checked')).map(function (i) { return i.value; });

                if (!eventos.length) {
                    toast('Elige al menos un evento.', 'error');
                    return;
                }

                var id = form.id.value;
                var payload = { nombre: form.nombre.value, url: form.url.value, eventos: eventos, activo: form.activo.checked };
                var esEdicion = !!id;

                fetch(esEdicion ? urlWebhook(id) : '{{ route('admin.integraciones.webhooks.store') }}', {
                    method: esEdicion ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                    .then(function (res) {
                        if (!res.ok) { toast(Object.values(res.data.errors || {})[0]?.[0] || 'No se pudo guardar el webhook.', 'error'); return; }
                        closeModal('webhookModal');
                        toast(esEdicion ? 'Webhook actualizado.' : 'Webhook creado.', 'success');
                        if (!esEdicion && res.data.secreto) {
                            document.getElementById('webhookSecretoValor').value = res.data.secreto;
                            openModal('webhookSecretoModal');
                        }
                        setTimeout(function () { window.location.reload(); }, esEdicion ? 50 : 300);
                    });
            });

            document.getElementById('webhookSecretoCopiar').addEventListener('click', function () {
                var input = document.getElementById('webhookSecretoValor');
                input.select();
                navigator.clipboard.writeText(input.value).then(function () { toast('Copiado.', 'success'); });
            });

            // ---------- Eliminar ----------
            document.querySelectorAll('[data-eliminar-webhook]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!window.confirm('¿Eliminar este webhook? Dejará de recibir eventos.')) return;
                    var id = btn.getAttribute('data-eliminar-webhook');
                    fetch(urlWebhook(id), {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    }).then(function (r) {
                        if (!r.ok) { toast('No se pudo eliminar.', 'error'); return; }
                        document.querySelector('[data-webhook-row="' + id + '"]').remove();
                        toast('Webhook eliminado.', 'success');
                    });
                });
            });

            // ---------- Probar ----------
            document.querySelectorAll('[data-probar-webhook]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-probar-webhook');
                    btn.disabled = true;
                    fetch(urlWebhook(id) + '/probar', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    }).then(function (r) { return r.json(); })
                        .then(function (res) {
                            var ok = res.data && res.data.estado === 'entregado';
                            toast(ok ? 'Prueba entregada correctamente.' : 'La prueba no se pudo entregar (revisa la URL).', ok ? 'success' : 'error');
                        })
                        .finally(function () { btn.disabled = false; });
                });
            });

            // ---------- Entregas ----------
            function pintarEntregas(webhookId, entregas) {
                var cont = document.getElementById('webhookEntregasBody');
                if (!entregas.length) {
                    cont.innerHTML = '<p style="color: var(--text-secondary);">Sin entregas todavía.</p>';
                    return;
                }

                var filas = entregas.map(function (e) {
                    var badgeClass = estadoBadgeClass[e.estado] || 'badge--neutral';
                    var payload = JSON.stringify(e.payload, null, 2).replace(/</g, '&lt;');
                    var respuesta = (e.respuesta || e.error || '').toString().replace(/</g, '&lt;');
                    var reintentar = e.estado !== 'entregado'
                        ? '<button type="button" class="btn btn--icon" data-reintentar-entrega="' + e.id + '" title="Reintentar"><i class="fa-solid fa-rotate-right"></i></button>'
                        : '';

                    return '<tr>'
                        + '<td>' + e.evento + '</td>'
                        + '<td><span class="badge ' + badgeClass + '">' + e.estado_label + '</span></td>'
                        + '<td>' + e.intentos + '</td>'
                        + '<td>' + (e.codigo_http ?? '—') + '</td>'
                        + '<td>' + (e.proximo_intento_en ?? '—') + '</td>'
                        + '<td><details><summary>Ver</summary><pre style="white-space:pre-wrap; font-size:.75rem; max-width:360px;">' + payload + (respuesta ? '\n\n' + respuesta : '') + '</pre></details></td>'
                        + '<td>' + reintentar + '</td>'
                        + '</tr>';
                }).join('');

                cont.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr>'
                    + '<th>Evento</th><th>Estado</th><th>Intentos</th><th>Código</th><th>Próximo intento</th><th>Payload/Respuesta</th><th></th>'
                    + '</tr></thead><tbody>' + filas + '</tbody></table></div>';

                cont.querySelectorAll('[data-reintentar-entrega]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var entregaId = btn.getAttribute('data-reintentar-entrega');
                        btn.disabled = true;
                        fetch('{{ url('admin/integraciones/webhooks/entregas') }}/' + entregaId + '/reintentar', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        }).then(function (r) { return r.json(); })
                            .then(function () {
                                toast('Reintento enviado.', 'success');
                                cargarEntregas(webhookId);
                            })
                            .finally(function () { btn.disabled = false; });
                    });
                });
            }

            function cargarEntregas(webhookId) {
                document.getElementById('webhookEntregasBody').innerHTML = '<p style="color: var(--text-secondary);">Cargando...</p>';
                fetch(urlWebhook(webhookId) + '/entregas', { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) { pintarEntregas(webhookId, res.data || []); });
            }

            document.querySelectorAll('[data-ver-entregas]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var id = btn.getAttribute('data-ver-entregas');
                    openModal('webhookEntregasModal');
                    cargarEntregas(id);
                });
            });
        })();
    </script>
@endsection
