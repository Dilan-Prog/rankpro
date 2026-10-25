{{-- Estado vacío de las pestañas que dependen de la fase: antes salían en blanco y parecía un error. --}}
<div class="card card--padded">
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-lock"></i></div>
        <p class="empty-state__text">Esta sección se habilita al llegar a la fase de Ejecución. Fase actual: {{ \App\Support\Labels::faseSeo($campana->fase_actual->value) }}.</p>
        <button type="button" class="btn btn--secondary" data-tab-link="fase">Ver fase actual</button>
    </div>
</div>
