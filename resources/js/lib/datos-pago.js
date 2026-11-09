/**
 * Detecta indicios de datos de pago en el texto de una sección del reporte.
 *
 * Por qué: el entregable va al cliente y alguien ya pegó a mano en una sección de
 * texto los últimos dígitos de una tarjeta y el saldo de la cuenta de anuncios.
 * Es una advertencia suave (heurística, puede haber falsos positivos), nunca bloquea.
 */

const PALABRAS = /\b(tarjeta|saldo|clabe|cuenta\s+de\s+anuncios)\b/i;
// "****1234", "•••• 1234", "xxxx-1234" o "terminada en 1234" / "terminación 1234".
const ULTIMOS_DIGITOS = /(?:[*•xX]{2,}[\s-]*\d{4}\b|\btermina(?:da|ción|cion)\s+en\s+\d{4}\b)/i;

/** Todos los textos (cadenas) de un valor anidado: contenido JSON, título, aviso. */
export function textosDe(valor, acumulado = []) {
  if (typeof valor === "string") acumulado.push(valor);
  else if (Array.isArray(valor)) valor.forEach((v) => textosDe(v, acumulado));
  else if (valor && typeof valor === "object") Object.values(valor).forEach((v) => textosDe(v, acumulado));
  return acumulado;
}

export function pareceDatoDePago(texto) {
  const t = String(texto || "");
  return PALABRAS.test(t) || ULTIMOS_DIGITOS.test(t);
}

/** `true` si el título, el aviso o cualquier texto del contenido parece un dato de pago. */
export function seccionPareceDatoDePago(partes) {
  return textosDe(partes).some(pareceDatoDePago);
}
