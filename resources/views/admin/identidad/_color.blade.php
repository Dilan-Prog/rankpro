{{-- Fila de la paleta. Los name="colores[i]..." se renumeran en identidad.js antes de enviar. --}}
<div class="identidad__color" data-color>
    <input type="color" class="identidad__muestra" value="{{ $hex }}" aria-label="Elegir color" data-color-selector>
    <div class="identidad__color-campos">
        <input type="text" class="input" name="colores[0][nombre]" value="{{ $nombre }}" maxlength="40" placeholder="Nombre" aria-label="Nombre del color" data-color-nombre>
        <input type="text" class="input identidad__hex" name="colores[0][hex]" value="{{ $hex }}" maxlength="7" placeholder="#0F9D6E" aria-label="Código hexadecimal" data-color-hex>
    </div>
    <button type="button" class="btn btn--icon" title="Quitar color" aria-label="Quitar color" data-color-quitar><i class="fa-solid fa-trash"></i></button>
</div>
