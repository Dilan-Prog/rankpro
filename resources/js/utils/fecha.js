/**
 * Formato único para MOSTRAR fechas en el panel: dd/mm/aaaa (es-MX).
 * Módulo puro (sin DOM) para poder probarlo con Node. Espejo de App\Support\Fecha.
 * Solo es para texto visible: lo que se envía al servidor o se compara sigue en ISO.
 */

const RE_ISO = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/;
const RE_CON_ZONA = /(?:Z|[+-]\d{2}:?\d{2})$/;

const dosDigitos = (n) => String(n).padStart(2, "0");

function fechaValida(a, m, d) {
  const f = new Date(Date.UTC(a, m - 1, d));
  return f.getUTCFullYear() === a && f.getUTCMonth() === m - 1 && f.getUTCDate() === d;
}

/**
 * "2026-12-05" (o con hora) → "05/12/2026". Se parte el texto a mano y no se usa
 * new Date("YYYY-MM-DD"): eso se interpreta en UTC y en México mostraría el día anterior.
 * Un valor vacío o ilegible devuelve `vacio`.
 */
export function formatearFecha(valor, vacio = "—") {
  const m = RE_ISO.exec(String(valor ?? "").trim());
  if (!m || !fechaValida(+m[1], +m[2], +m[3])) return vacio;
  return `${m[3]}/${m[2]}/${m[1]}`;
}

/**
 * "2026-12-05 14:30:00" → "05/12/2026 14:30". Sin zona explícita la hora se muestra tal cual
 * (así la guarda el servidor); con "Z"/offset se pasa a la hora local del navegador.
 */
export function formatearFechaHora(valor, vacio = "—") {
  const texto = String(valor ?? "").trim();
  const m = RE_ISO.exec(texto);
  if (!m || !fechaValida(+m[1], +m[2], +m[3])) return vacio;

  if (m[4] !== undefined && RE_CON_ZONA.test(texto)) {
    const f = new Date(texto);
    if (!Number.isNaN(f.getTime())) {
      return `${dosDigitos(f.getDate())}/${dosDigitos(f.getMonth() + 1)}/${f.getFullYear()} ${dosDigitos(f.getHours())}:${dosDigitos(f.getMinutes())}`;
    }
  }

  return `${m[3]}/${m[2]}/${m[1]}${m[4] !== undefined ? ` ${m[4]}:${m[5]}` : ""}`;
}
