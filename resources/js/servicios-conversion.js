/**
 * Interactividad de las landings de servicio (SEM y SEO).
 *
 * Sustituye al React del prototipo: acordeon de objeciones, pestanas de la
 * grafica de resultados y calculadora de ROI. Todo progresivo: si el JS falla,
 * las respuestas del acordeon quedan visibles y la grafica muestra su serie
 * por defecto, porque el HTML se sirve ya renderizado desde Blade.
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

/* ------------------------------------------------------------ grafica de resultados */

/** Construye la ruta de un area chart suavizado a partir de los valores. */
function rutaArea(valores, w, h, pad) {
    const max = Math.max(...valores);
    const min = Math.min(...valores);
    const rango = max - min || 1;
    const paso = (w - pad * 2) / (valores.length - 1);

    const puntos = valores.map((v, i) => [
        pad + i * paso,
        h - pad - ((v - min) / rango) * (h - pad * 2),
    ]);

    // Curva de Catmull-Rom convertida a Bezier, igual de suave que el "monotone"
    // de recharts pero sin traer la libreria entera.
    let d = `M ${puntos[0][0]} ${puntos[0][1]}`;
    for (let i = 0; i < puntos.length - 1; i++) {
        const p0 = puntos[i === 0 ? 0 : i - 1];
        const p1 = puntos[i];
        const p2 = puntos[i + 1];
        const p3 = puntos[i + 2] ?? p2;
        d += ` C ${p1[0] + (p2[0] - p0[0]) / 6} ${p1[1] + (p2[1] - p0[1]) / 6},` +
             ` ${p2[0] - (p3[0] - p1[0]) / 6} ${p2[1] - (p3[1] - p1[1]) / 6},` +
             ` ${p2[0]} ${p2[1]}`;
    }

    return { d, puntos };
}

function initChart() {
    const raiz = document.querySelector('[data-cv-chart]');

    if (!raiz) {
        return;
    }

    const series = JSON.parse(raiz.dataset.cvChart);
    const svg = raiz.querySelector('svg');
    const area = svg.querySelector('[data-area]');
    const linea = svg.querySelector('[data-linea]');
    const puntosG = svg.querySelector('[data-puntos]');
    const etiquetasY = svg.querySelectorAll('[data-y]');
    const label = raiz.querySelector('[data-label]');
    const hint = raiz.querySelector('[data-hint]');
    const valor = raiz.querySelector('[data-valor]');
    const delta = raiz.querySelector('[data-delta]');

    const W = 640, H = 260, PAD = 28;

    const pinta = (clave) => {
        const s = series[clave];
        const valores = s.data.map((p) => p.v);
        const { d, puntos } = rutaArea(valores, W, H, PAD);

        linea.setAttribute('d', d);
        area.setAttribute('d', `${d} L ${puntos.at(-1)[0]} ${H - PAD} L ${puntos[0][0]} ${H - PAD} Z`);

        puntosG.innerHTML = puntos.map(([x, y], i) =>
            `<circle cx="${x}" cy="${y}" r="${i === puntos.length - 1 ? 5 : 3}" fill="#0F9D6E"${
                i === puntos.length - 1 ? ' stroke="#fff" stroke-width="2"' : ''
            }><title>${s.data[i].m}: ${s.data[i].v.toLocaleString('es-MX')}${s.suffix}</title></circle>`
        ).join('');

        const max = Math.max(...valores), min = Math.min(...valores);
        etiquetasY.forEach((t, i) => {
            const frac = 1 - i / (etiquetasY.length - 1);
            t.textContent = Math.round(min + (max - min) * frac).toLocaleString('es-MX');
        });

        const primero = valores[0], ultimo = valores.at(-1);
        const pct = Math.round(((ultimo - primero) / primero) * 100);

        label.textContent = s.label;
        hint.textContent = s.hint;
        valor.textContent = ultimo.toLocaleString('es-MX') + s.suffix;
        delta.textContent = `${pct > 0 ? '+' : ''}${pct}% vs. mes 0`;
    };

    raiz.querySelectorAll('.cv-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            raiz.querySelectorAll('.cv-tab').forEach((t) => t.setAttribute('aria-selected', 'false'));
            tab.setAttribute('aria-selected', 'true');
            pinta(tab.dataset.serie);
        });
    });

    pinta(raiz.querySelector('.cv-tab[aria-selected="true"]')?.dataset.serie ?? Object.keys(series)[0]);
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
    initChart();
    initCalculadora();
    initConfigurador();
});
