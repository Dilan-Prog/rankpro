/**
 * Pantalla de integraciones de un cliente: el token se ve enmascarado por
 * defecto (Mostrar/Ocultar), Copiar entrega siempre el snippet completo y
 * "Regenerar token" abre un modal propio en lugar del confirm() nativo.
 * El snippet completo vive en data-completo (el token es público por diseño,
 * va en el HTML del sitio del cliente; solo se evita pintarlo en pantalla).
 */
import { enmascararToken } from './utils/enmascarar-token.js';

function copiarTexto(texto) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(texto);
  }
  // Respaldo para http sin contexto seguro (hosting o dominios locales sin https).
  return new Promise((resolver, rechazar) => {
    const area = document.createElement('textarea');
    area.value = texto;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    const ok = document.execCommand('copy');
    area.remove();
    ok ? resolver() : rechazar(new Error('copiar'));
  });
}

function avisar(mensaje, tipo) {
  if (window.AgencyOS && window.AgencyOS.toast) window.AgencyOS.toast(mensaje, tipo);
}

document.querySelectorAll('[data-token-bloque]').forEach((bloque) => {
  const completo = bloque.dataset.completo || '';
  const texto = bloque.querySelector('[data-token-texto]');
  const botonMostrar = bloque.querySelector('[data-token-mostrar]');
  const botonCopiar = bloque.querySelector('[data-token-copiar]');
  let visible = false;

  const pintar = () => {
    const valor = visible ? completo : enmascararToken(completo);
    if ('value' in texto) texto.value = valor; else texto.textContent = valor;
    if (botonMostrar) {
      botonMostrar.setAttribute('aria-pressed', visible ? 'true' : 'false');
      botonMostrar.innerHTML = visible
        ? '<i class="fa-solid fa-eye-slash"></i> Ocultar'
        : '<i class="fa-solid fa-eye"></i> Mostrar';
    }
  };

  if (botonMostrar) botonMostrar.addEventListener('click', () => { visible = !visible; pintar(); });
  if (botonCopiar) {
    botonCopiar.addEventListener('click', () => {
      copiarTexto(completo).then(
        () => avisar('Script copiado completo.'),
        () => avisar('No se pudo copiar; usa Mostrar y copia a mano.', 'error'),
      );
    });
  }
  pintar();
});

document.querySelectorAll('[data-abrir-modal]').forEach((boton) => {
  boton.addEventListener('click', () => {
    const id = boton.dataset.abrirModal;
    if (window.AgencyOS) window.AgencyOS.openModal(id);
    // El foco va a Cancelar para que Enter no regenere por accidente.
    const cancelar = document.querySelector('#' + id + ' [data-modal-foco]');
    if (cancelar) cancelar.focus();
  });
});
