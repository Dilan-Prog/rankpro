/**
 * Agendar reunión (sitio público, sin sesión): calendario de mes + horarios +
 * datos + confirmación, todo en una sola pantalla.
 *
 * El nodo raíz [data-agendar] es el contenedor MÁS EXTERNO de TODO el flujo
 * (incluye el panel lateral de resumen/temas Y el contenido de la derecha),
 * así que root.querySelector siempre alcanza cualquier botón/panel sin
 * importar en qué columna del layout viva (lección aprendida: si el root
 * fuera solo la tarjeta derecha, los botones del lateral quedarían fuera de
 * alcance y root.querySelector devolvería null en silencio).
 *
 * No existe una ruta para pedir la disponibilidad de un mes completo de una
 * sola vez. En vez de disparar un fetch por cada día visible (podrían ser 45+
 * con dias_visibles grande), el calendario de mes se pinta con todos los días
 * futuros (dentro del horizonte) como "elegibles" y la disponibilidad real de
 * cada día se resuelve SOLO al hacer clic sobre él — si ese día no tiene
 * horarios, se muestra "sin horarios este día" y el usuario prueba otro. Es
 * la opción más simple y la más barata en peticiones; el mockup original
 * premarca los días con huecos porque corre 100% en el navegador sobre
 * localStorage (sin backend real de por medio).
 *
 * El campo "tema de la consultoría" (chips, solo UI) no existe como columna
 * en la BD: si el usuario elige uno, se antepone a las notas libres como
 * "Tema: {tema}\n\n{notas}" (o solo "Tema: {tema}" si no hay notas) dentro
 * del mismo campo `notas` que ya recibía el backend. No se manda ningún
 * campo nuevo en el payload.
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-agendar]');
    if (!root) {
        return;
    }

    const urlDisponibilidad = root.dataset.urlDisponibilidad;
    const urlAgendar = root.dataset.urlAgendar;
    const duracionMinutos = parseInt(root.dataset.duracionMinutos, 10) || 30;
    const diasVisibles = parseInt(root.dataset.diasVisibles, 10) || 30;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    const pasos = {
        elegir: root.querySelector('[data-agendar-paso="elegir"]'),
        formulario: root.querySelector('[data-agendar-paso="formulario"]'),
        confirmacion: root.querySelector('[data-agendar-paso="confirmacion"]'),
    };

    const elMesTitulo = root.querySelector('[data-agendar-mes-titulo]');
    const elMesPrev = root.querySelector('[data-agendar-mes-prev]');
    const elMesNext = root.querySelector('[data-agendar-mes-next]');
    const elCalendario = root.querySelector('[data-agendar-calendario]');
    const elFechaElegida = root.querySelector('[data-agendar-fecha-elegida]');
    const elSinFecha = root.querySelector('[data-agendar-sin-fecha]');
    const elHorarios = root.querySelector('[data-agendar-horarios]');
    const elSiguiente = root.querySelector('[data-agendar-siguiente]');
    const elResumenElegido = root.querySelector('[data-agendar-resumen-elegido]');
    const elResumenElegidoTexto = root.querySelector('[data-agendar-resumen-elegido-texto]');
    const elTemas = root.querySelectorAll('[data-agendar-tema]');
    const elCambiarHorario = root.querySelector('[data-agendar-cambiar-horario]');
    const elConfirmarTexto = root.querySelector('[data-agendar-confirmar-texto]');
    const elAviso = root.querySelector('[data-agendar-aviso]');
    const elConfirmacion = root.querySelector('[data-agendar-confirmacion]');
    const form = root.querySelector('[data-agendar-form]');
    const btnEnviar = root.querySelector('[data-agendar-enviar]');

    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const horizonte = addDays(hoy, diasVisibles);

    let mesActual = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    let fechaElegida = null;
    let horaElegida = null;
    let temaElegido = null;

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function ymd(d) {
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    }

    function parseYmd(s) {
        const [anio, mes, dia] = s.split('-').map(Number);
        return new Date(anio, mes - 1, dia);
    }

    function addDays(d, n) {
        const x = new Date(d);
        x.setDate(x.getDate() + n);
        return x;
    }

    /** "2026-09-23" -> "martes 23 de septiembre" */
    function formatearFechaLarga(fechaISO) {
        const fecha = parseYmd(fechaISO);
        return fecha.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
    }

    /** "09:00" -> "9:00 AM" */
    function formatearHora12(horaHHMM) {
        const [horas, minutos] = horaHHMM.split(':').map(Number);
        const periodo = horas >= 12 ? 'PM' : 'AM';
        const horas12 = horas % 12 === 0 ? 12 : horas % 12;
        return `${horas12}:${String(minutos).padStart(2, '0')} ${periodo}`;
    }

    function mostrarPaso(nombre) {
        Object.entries(pasos).forEach(([clave, el]) => {
            if (!el) {
                return;
            }
            el.hidden = clave !== nombre;
        });
    }

    function actualizarResumenLateral() {
        if (fechaElegida && horaElegida) {
            elResumenElegidoTexto.textContent = `${formatearFechaLarga(fechaElegida)}, ${formatearHora12(horaElegida)}`;
            elResumenElegido.hidden = false;
        } else {
            elResumenElegido.hidden = true;
        }
    }

    // ---------- Chips de tema (opcional, no bloquea avanzar) ----------

    elTemas.forEach((btn) => {
        btn.addEventListener('click', () => {
            const valor = btn.dataset.agendarTema;
            temaElegido = temaElegido === valor ? null : valor;
            elTemas.forEach((otro) => otro.classList.toggle('is-activo', otro.dataset.agendarTema === temaElegido));
        });
    });

    // ---------- Calendario de mes ----------

    function pintarCalendario() {
        elMesTitulo.textContent = `${MESES[mesActual.getMonth()]} ${mesActual.getFullYear()}`;

        const primerDiaSemana = (mesActual.getDay() + 6) % 7; // semana inicia en lunes
        const diasEnMes = new Date(mesActual.getFullYear(), mesActual.getMonth() + 1, 0).getDate();

        elCalendario.innerHTML = '';

        for (let i = 0; i < primerDiaSemana; i++) {
            const vacio = document.createElement('div');
            elCalendario.appendChild(vacio);
        }

        for (let n = 1; n <= diasEnMes; n++) {
            const fecha = new Date(mesActual.getFullYear(), mesActual.getMonth(), n);
            const fechaISO = ymd(fecha);
            const pasado = fecha < hoy || fecha > horizonte;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'agendar__dia-celda';
            btn.textContent = String(n);
            btn.disabled = pasado;

            if (pasado) {
                btn.classList.add('is-pasado');
            }
            if (fechaISO === ymd(hoy)) {
                btn.classList.add('is-hoy');
            }
            if (fechaISO === fechaElegida) {
                btn.classList.add('is-elegido');
            }

            if (!pasado) {
                btn.addEventListener('click', () => elegirFecha(fechaISO));
            }

            elCalendario.appendChild(btn);
        }

        const puedeAnterior = mesActual > new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const puedeSiguiente = new Date(mesActual.getFullYear(), mesActual.getMonth() + 1, 1) <= horizonte;
        elMesPrev.disabled = !puedeAnterior;
        elMesNext.disabled = !puedeSiguiente;
    }

    elMesPrev.addEventListener('click', () => {
        mesActual = new Date(mesActual.getFullYear(), mesActual.getMonth() - 1, 1);
        pintarCalendario();
    });

    elMesNext.addEventListener('click', () => {
        mesActual = new Date(mesActual.getFullYear(), mesActual.getMonth() + 1, 1);
        pintarCalendario();
    });

    // ---------- Horarios del día elegido ----------

    function elegirFecha(fechaISO) {
        fechaElegida = fechaISO;
        horaElegida = null;
        pintarCalendario();

        elSinFecha.hidden = true;
        elFechaElegida.textContent = formatearFechaLarga(fechaISO);
        elFechaElegida.hidden = false;
        elSiguiente.hidden = true;
        elHorarios.hidden = false;
        elHorarios.innerHTML = '<p class="agendar__cargando">Buscando horarios disponibles…</p>';
        actualizarResumenLateral();

        fetch(`${urlDisponibilidad}?fecha=${encodeURIComponent(fechaISO)}`, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((data) => {
                if (fechaElegida !== fechaISO) {
                    return; // el usuario ya cambió de día mientras esto cargaba
                }

                const horarios = data.horarios || [];
                if (horarios.length === 0) {
                    elHorarios.innerHTML = '<p class="agendar__vacio">Sin horarios este día, prueba con otro.</p>';
                    return;
                }

                elHorarios.innerHTML = '';
                horarios.forEach((hora) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'agendar__horario';
                    btn.dataset.hora = hora;
                    btn.textContent = formatearHora12(hora);
                    btn.addEventListener('click', () => elegirHora(fechaISO, hora, btn));
                    elHorarios.appendChild(btn);
                });
            })
            .catch(() => {
                if (fechaElegida === fechaISO) {
                    elHorarios.innerHTML = '<p class="agendar__vacio">No pudimos cargar los horarios, intenta de nuevo.</p>';
                }
            });
    }

    function elegirHora(fechaISO, horaHHMM, btnElegido) {
        horaElegida = horaHHMM;
        elHorarios.querySelectorAll('.agendar__horario').forEach((btn) => {
            btn.classList.toggle('is-elegido', btn === btnElegido);
        });
        elSiguiente.hidden = false;
        actualizarResumenLateral();
    }

    elSiguiente.addEventListener('click', () => {
        if (!fechaElegida || !horaElegida) {
            return;
        }
        elConfirmarTexto.textContent = `${formatearFechaLarga(fechaElegida)}, ${formatearHora12(horaElegida)}`;
        limpiarErrores();
        mostrarPaso('formulario');
    });

    if (elCambiarHorario) {
        elCambiarHorario.addEventListener('click', () => {
            mostrarPaso('elegir');
        });
    }

    // ---------- Formulario ----------

    function limpiarErrores() {
        form.querySelectorAll('[data-error-for]').forEach((span) => {
            span.textContent = '';
        });
        form.querySelectorAll('.is-invalido').forEach((campo) => campo.classList.remove('is-invalido'));
        elAviso.hidden = true;
        elAviso.textContent = '';
    }

    function pintarErrores(errores) {
        Object.keys(errores || {}).forEach((campo) => {
            const span = form.querySelector(`[data-error-for="${campo}"]`);
            const input = form.querySelector(`[name="${campo}"]`);
            if (span) {
                span.textContent = errores[campo][0];
            }
            if (input) {
                input.classList.add('is-invalido');
            }
        });
    }

    function validarFormulario(datos) {
        const errores = {};
        const nombre = (datos.get('nombre') || '').trim();
        const email = (datos.get('email') || '').trim();
        const telefono = (datos.get('telefono') || '').replace(/\D/g, '');

        if (nombre.length < 2) {
            errores.nombre = ['Escribe tu nombre completo.'];
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errores.email = ['Escribe un correo válido.'];
        }
        if (telefono.length < 8) {
            errores.telefono = ['Escribe un número de WhatsApp válido.'];
        }

        return errores;
    }

    if (form) {
        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            if (!fechaElegida || !horaElegida) {
                return;
            }

            limpiarErrores();

            const datos = new FormData(form);
            const errores = validarFormulario(datos);
            if (Object.keys(errores).length > 0) {
                pintarErrores(errores);
                elAviso.hidden = false;
                elAviso.textContent = 'Revisa los campos marcados.';
                return;
            }

            let notas = (datos.get('notas') || '').trim();
            if (temaElegido) {
                notas = notas ? `Tema: ${temaElegido}\n\n${notas}` : `Tema: ${temaElegido}`;
            }

            const payload = {
                nombre: datos.get('nombre'),
                email: datos.get('email'),
                telefono: datos.get('telefono') || null,
                notas: notas || null,
                inicia_en: `${fechaElegida} ${horaElegida}`,
            };

            btnEnviar.disabled = true;

            fetch(urlAgendar, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            })
                .then((res) => res.json().then((data) => ({ status: res.status, data })))
                .then(({ status, data }) => {
                    if (status !== 200 || !data.ok) {
                        if (status === 422 && data.errors) {
                            pintarErrores(data.errors);
                        }
                        elAviso.hidden = false;
                        elAviso.textContent = data.message || 'Ese horario ya no está disponible, elige otro.';
                        return;
                    }

                    mostrarConfirmacion(data, datos);
                })
                .catch(() => {
                    elAviso.hidden = false;
                    elAviso.textContent = 'No pudimos agendar tu reunión, intenta de nuevo.';
                })
                .finally(() => {
                    btnEnviar.disabled = false;
                });
        });
    }

    // ---------- Confirmación: resumen + Google Calendar + .ics ----------

    function fechaHoraElegidas() {
        const inicio = parseYmd(fechaElegida);
        const [h, m] = horaElegida.split(':').map(Number);
        inicio.setHours(h, m, 0, 0);
        const fin = new Date(inicio.getTime() + duracionMinutos * 60000);
        return { inicio, fin };
    }

    function formatoICalendar(fecha) {
        return `${fecha.getFullYear()}${pad(fecha.getMonth() + 1)}${pad(fecha.getDate())}T${pad(fecha.getHours())}${pad(fecha.getMinutes())}00`;
    }

    function urlGoogleCalendar() {
        const { inicio, fin } = fechaHoraElegidas();
        const detalles = temaElegido ? `Tema: ${temaElegido}` : 'Consultoría estratégica con RankPro.';
        const params = new URLSearchParams({
            action: 'TEMPLATE',
            text: 'Consultoría RankPro',
            dates: `${formatoICalendar(inicio)}/${formatoICalendar(fin)}`,
            details: detalles,
        });
        return `https://calendar.google.com/calendar/render?${params.toString()}`;
    }

    function descargarIcs() {
        const { inicio, fin } = fechaHoraElegidas();
        const detalles = temaElegido ? `Tema: ${temaElegido}` : 'Consultoria estrategica con RankPro.';
        const lineas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'BEGIN:VEVENT',
            `UID:agendar-${Date.now()}@rankpro`,
            `DTSTART:${formatoICalendar(inicio)}`,
            `DTEND:${formatoICalendar(fin)}`,
            'SUMMARY:Consultoria RankPro',
            `DESCRIPTION:${detalles}`,
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        const blob = new Blob([lineas.join('\r\n')], { type: 'text/calendar' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'consultoria-rankpro.ics';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function mostrarConfirmacion(data, datosFormulario) {
        const nombre = (datosFormulario.get('nombre') || '').trim().split(' ')[0] || '';
        const email = (datosFormulario.get('email') || '').trim();

        elConfirmacion.innerHTML = '';

        const icono = document.createElement('div');
        icono.className = 'agendar__confirmacion-icono';
        icono.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M20 6L9 17l-5-5"></path></svg>';
        elConfirmacion.appendChild(icono);

        const titulo = document.createElement('h2');
        titulo.className = 'agendar__confirmacion-titulo';
        titulo.textContent = nombre ? `¡Listo, ${nombre}!` : '¡Reunión confirmada!';
        elConfirmacion.appendChild(titulo);

        const mensaje = document.createElement('p');
        mensaje.className = 'agendar__confirmacion-texto';
        mensaje.innerHTML = data.mensaje
            ? escapeHtml(data.mensaje)
            : `Tu consultoría quedó agendada. Te enviamos la confirmación a <strong>${escapeHtml(email)}</strong>.`;
        elConfirmacion.appendChild(mensaje);

        const tarjeta = document.createElement('div');
        tarjeta.className = 'agendar__confirmacion-cita';
        tarjeta.innerHTML = `
            <div class="agendar__confirmacion-cita-etiqueta">TU CITA</div>
            <div class="agendar__confirmacion-cita-fecha">${escapeHtml(formatearFechaLarga(fechaElegida))}</div>
            <div class="agendar__confirmacion-cita-hora">${escapeHtml(formatearHora12(horaElegida))} · ${duracionMinutos} min · Videollamada</div>
            ${temaElegido ? `<div class="agendar__confirmacion-cita-tema">Tema: ${escapeHtml(temaElegido)}</div>` : ''}
        `;
        elConfirmacion.appendChild(tarjeta);

        const acciones = document.createElement('div');
        acciones.className = 'agendar__confirmacion-acciones';

        const btnGoogle = document.createElement('a');
        btnGoogle.className = 'btn btn-outline';
        btnGoogle.href = urlGoogleCalendar();
        btnGoogle.target = '_blank';
        btnGoogle.rel = 'noopener noreferrer';
        btnGoogle.textContent = 'Google Calendar';
        acciones.appendChild(btnGoogle);

        const btnIcs = document.createElement('button');
        btnIcs.type = 'button';
        btnIcs.className = 'btn btn-outline';
        btnIcs.textContent = 'Outlook / Apple (.ics)';
        btnIcs.addEventListener('click', descargarIcs);
        acciones.appendChild(btnIcs);

        elConfirmacion.appendChild(acciones);

        if (data.cancelar_url) {
            const link = document.createElement('a');
            link.className = 'agendar__confirmacion-cancelar';
            link.href = data.cancelar_url;
            link.textContent = 'Cancelar esta reunión';
            elConfirmacion.appendChild(link);
        }

        const btnReiniciar = document.createElement('button');
        btnReiniciar.type = 'button';
        btnReiniciar.className = 'agendar__confirmacion-reiniciar';
        btnReiniciar.textContent = 'Agendar otra cita';
        btnReiniciar.addEventListener('click', reiniciarFlujo);
        elConfirmacion.appendChild(btnReiniciar);

        mostrarPaso('confirmacion');
    }

    function reiniciarFlujo() {
        fechaElegida = null;
        horaElegida = null;
        temaElegido = null;
        elTemas.forEach((btn) => btn.classList.remove('is-activo'));
        elFechaElegida.hidden = true;
        elSinFecha.hidden = false;
        elHorarios.hidden = true;
        elHorarios.innerHTML = '';
        elSiguiente.hidden = true;
        actualizarResumenLateral();
        form.reset();
        limpiarErrores();
        mesActual = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        pintarCalendario();
        mostrarPaso('elegir');
    }

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }

    pintarCalendario();
    mostrarPaso('elegir');
});
