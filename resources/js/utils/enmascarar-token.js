/**
 * Máscara del token de tracking (rp_live_…): se separa en un módulo puro para
 * poder probarla con Node sin DOM. Debe coincidir con la máscara de PHP de la
 * vista (admin/integraciones/cliente.blade.php), que es la que se ve sin JS.
 */
export const PUNTOS_MASCARA = '••••••••••';

/** Sustituye el cuerpo del token por puntos y conserva prefijo y últimos 4 caracteres. */
export function enmascararToken(texto) {
  return String(texto).replace(/(rp_live_)([A-Za-z0-9]{8,})/g, (_, prefijo, cuerpo) => prefijo + PUNTOS_MASCARA + cuerpo.slice(-4));
}
