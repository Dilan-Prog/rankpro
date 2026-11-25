/**
 * Interactividad de las páginas de servicio: acordeón de preguntas, calculadora
 * de ROI y configurador. Todo progresivo: el HTML se sirve ya renderizado.
 */

const money = (n) => '$' + Math.round(n).toLocaleString('es-MX');

/* ------------------------------------------------------------ acordeon */

function initFaq() {
    document.querySelectorAll('.cv-faq__trigger').forEach((trigger) => {
        const panel = document.getElementById(trigger.getAttribute('aria-controls'));

        if (!panel) {
            return;
        }

        // Sin JS los paneles se sirven abiertos; al arrancar cerramos los que
        // no sean el primero para reproducir el comportamiento del diseno.
        trigger.addEventListener('click', () => {
            const abierto = trigger.getAttribute('aria-expanded') === 'true';

            document.querySelectorAll('.cv-faq__trigger').forEach((otro) => {
                const otroPanel = document.getElementById(otro.getAttribute('aria-controls'));
                otro.setAttribute('aria-expanded', 'false');
                if (otroPanel) otroPanel.hidden = true;
            });

            if (!abierto) {
                trigger.setAttribute('aria-expanded', 'true');
                panel.hidden = false;
            }
        });
    });

    // Estado inicial: solo el primero abierto.
    document.querySelectorAll('.cv-faq').forEach((faq) => {
        faq.querySelectorAll('.cv-faq__trigger').forEach((trigger, i) => {
            const panel = document.getElementById(trigger.getAttribute('aria-controls'));
            trigger.setAttribute('aria-expanded', i === 0 ? 'true' : 'false');
            if (panel) panel.hidden = i !== 0;
        });
    });
}

/* ------------------------------------------------------------ calculadora de ROI */

function initCalculadora() {
    const raiz = document.querySelector('[data-cv-calc]');

    if (!raiz) {
        return;
    }

    const presupuesto = raiz.querySelector('[data-rango-presupuesto]');
    const ticket = raiz.querySelector('[data-rango-ticket]');
    const salidas = {
        presupuesto: raiz.querySelector('[data-out-presupuesto]'),
        ticket: raiz.querySelector('[data-out-ticket]'),
        clics: raiz.querySelector('[data-out-clics]'),
        leads: raiz.querySelector('[data-out-leads]'),
        ventas: raiz.querySelector('[data-out-ventas]'),
        roas: raiz.querySelector('[data-out-roas]'),
        ingresos: raiz.querySelector('[data-out-ingresos]'),
    };

    // Mismos supuestos que el prototipo: CPC $14, 6.2% clic->lead, 22% cierre.
    const CPC = 14, TASA_LEAD = 0.062, TASA_CIERRE = 0.22;

    const calcula = () => {
        const b = Number(presupuesto.value);
        const t = Number(ticket.value);

        const clics = Math.round((b * 1000) / CPC);
        const leads = Math.round(clics * TASA_LEAD);
        const ventas = Math.round(leads * TASA_CIERRE);
        const ingresos = ventas * t;
        const fee = b <= 50 ? 12900 : b <= 200 ? 24900 : 39900;
        const roas = ingresos / (b * 1000 + fee);

        salidas.presupuesto.textContent = '$' + b + 'k MXN';
        salidas.ticket.textContent = money(t);
        salidas.clics.textContent = clics.toLocaleString('es-MX');
        salidas.leads.textContent = leads.toLocaleString('es-MX');
        salidas.ventas.textContent = ventas.toLocaleString('es-MX');
        salidas.roas.textContent = roas.toFixed(1) + 'x';
        salidas.ingresos.textContent = money(ingresos);
    };

    presupuesto.addEventListener('input', calcula);
    ticket.addEventListener('input', calcula);
    calcula();
}


/* ------------------------------------------------------------ configurador de proyecto web */

function initConfigurador() {
    const raiz = document.querySelector('[data-cv-config]');

    if (!raiz) {
        return;
    }

    const cfg = JSON.parse(raiz.dataset.cvConfig);
    const wa = raiz.dataset.wa;

    const estado = {
        tipo: raiz.querySelector('.cv-tipo[aria-pressed="true"]')?.dataset.tipo ?? Object.keys(cfg.tipos)[0],
        paginas: Number(raiz.querySelector('[data-rango-paginas]').value),
        extras: new Set([...raiz.querySelectorAll('.cv-chip[aria-pressed="true"]')].map((c) => c.dataset.feature)),
        rush: raiz.querySelector('[data-switch-rush]').getAttribute('aria-pressed') === 'true',
    };

    const out = (k) => raiz.querySelector(`[data-out-${k}]`);
    const gantt = document.querySelector('[data-cv-gantt]');

    const calcula = () => {
        const t = cfg.tipos[estado.tipo];
        const extras = cfg.features.filter((f) => estado.extras.has(f.key));
        const costoExtras = extras.reduce((s, f) => s + f.costo, 0);
        const semanasExtras = extras.reduce((s, f) => s + f.semanas, 0);
        const paginasExtra = Math.max(0, estado.paginas - 5) * 6200;
        const subtotal = t.base + paginasExtra + costoExtras;
        const total = estado.rush ? subtotal * 1.28 : subtotal;
        const semanas = Math.max(
            2,
            Math.round((t.semanas + semanasExtras + Math.floor(Math.max(0, estado.paginas - 5) / 4)) * (estado.rush ? 0.7 : 1))
        );

        out('paginas').textContent = estado.paginas;
        out('rango').textContent = `${money(total * 0.9)} – ${money(total * 1.15)}`;
        out('semanas').textContent = semanas;
        out('personas').textContent = 2 + Math.min(4, extras.length);

        // Desglose
        const filas = [`<li><span>${t.label}</span><span>${money(t.base)}</span></li>`];
        if (paginasExtra > 0) {
            filas.push(`<li><span>${estado.paginas - 5} plantillas extra</span><span>${money(paginasExtra)}</span></li>`);
        }
        extras.forEach((f) => filas.push(`<li><span>${f.label}</span><span>${money(f.costo)}</span></li>`));
        if (estado.rush) {
            filas.push('<li class="is-rush"><span>Entrega acelerada</span><span>+28%</span></li>');
        }
        out('desglose').innerHTML = filas.join('');

        // Cronograma: las fases se reparten sobre las semanas calculadas.
        if (gantt) {
            gantt.querySelectorAll('[data-fase]').forEach((row) => {
                const peso = Number(row.dataset.peso);
                row.querySelector('[data-fase-sem]').textContent = Math.max(1, Math.round(semanas * peso)) + ' sem';
            });
            const titulo = document.querySelector('[data-out-semanas-titulo]');
            if (titulo) titulo.textContent = semanas;
        }

        // El CTA lleva la configuracion al chat: el prototipo la perdia al enviar
        // un formulario que no mandaba nada a ningun lado.
        const resumen = [
            'Hola RankPro, armé una cotización en el sitio:',
            `• Proyecto: ${t.label}`,
            `• Plantillas: ${estado.paginas}`,
            `• Funciones: ${extras.length ? extras.map((f) => f.label).join(', ') : 'ninguna extra'}`,
            estado.rush ? '• Entrega acelerada: sí' : '• Entrega acelerada: no',
            `• Rango estimado: ${money(total * 0.9)} – ${money(total * 1.15)} MXN`,
            `• Plazo: ${semanas} semanas`,
            '',
            'Me gustaría la propuesta detallada.',
        ].join('\n');

        raiz.querySelectorAll('[data-cta-wa]').forEach((a) => {
            a.href = `${wa}?text=${encodeURIComponent(resumen)}`;
        });
    };

    raiz.querySelectorAll('.cv-tipo').forEach((btn) => {
        btn.addEventListener('click', () => {
            raiz.querySelectorAll('.cv-tipo').forEach((b) => b.setAttribute('aria-pressed', 'false'));
            btn.setAttribute('aria-pressed', 'true');
            estado.tipo = btn.dataset.tipo;
            calcula();
        });
    });

    raiz.querySelector('[data-rango-paginas]').addEventListener('input', (e) => {
        estado.paginas = Number(e.target.value);
        calcula();
    });

    raiz.querySelectorAll('.cv-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            const on = chip.getAttribute('aria-pressed') === 'true';
            chip.setAttribute('aria-pressed', String(!on));
            estado.extras[on ? 'delete' : 'add'](chip.dataset.feature);
            calcula();
        });
    });

    const rush = raiz.querySelector('[data-switch-rush]');
    rush.addEventListener('click', () => {
        estado.rush = rush.getAttribute('aria-pressed') !== 'true';
        rush.setAttribute('aria-pressed', String(estado.rush));
        calcula();
    });

    calcula();
}

document.addEventListener('DOMContentLoaded', () => {
    initFaq();
    initCalculadora();
    initConfigurador();
});
