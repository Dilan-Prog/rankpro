{{-- Importación de clientes por CSV (clientes-asistente.js). La vista previa no guarda nada; importar crea solo las filas nuevas. --}}
<div class="modal-overlay" id="importarClientesModal" hidden>
    <div class="modal modal--moderno" role="dialog" aria-modal="true" aria-labelledby="importarClientesTitulo">
        <form id="importarClientesForm" class="form-moderno" novalidate enctype="multipart/form-data"
              data-url-vista-previa="{{ route('admin.clientes.importar.vista-previa') }}"
              data-url-importar="{{ route('admin.clientes.importar.store') }}">
            @csrf
            <div class="modal--moderno__cabecera">
                <div>
                    <h2 id="importarClientesTitulo" class="modal--moderno__titulo">Importar clientes</h2>
                    <p class="modal--moderno__subtitulo">Pega o sube un CSV. Primero verás una vista previa; no se guarda nada hasta confirmar.</p>
                </div>
                <button type="button" class="modal--moderno__cerrar" data-modal-close="importarClientesModal" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal--moderno__cuerpo">
                <p class="form-moderno__ayuda">
                    Columnas: <code>nombre_comercial, contacto, email, telefono, giro, sitio_web, ciudad</code>. Acepta coma, punto y coma o tabulador, con o sin encabezado.
                    Giros válidos: {{ implode(', ', \App\Support\Plantillas\Giros::etiquetas()) }}.
                    Desde Google Sheets: Archivo, Publicar en la web, CSV, y pega el contenido o sube el archivo descargado.
                    Los duplicados (mismo correo o mismo nombre comercial) no se crean; las filas sin contacto, correo, WhatsApp o renovación se crean con perfil incompleto.
                </p>
                <div class="form-moderno__campo">
                    <label class="form-moderno__etiqueta" for="imp_texto">Pegar CSV</label>
                    <textarea class="form-moderno__control form-moderno__control--area" id="imp_texto" name="texto" rows="6" placeholder="nombre_comercial,contacto,email,telefono,giro,sitio_web,ciudad"></textarea>
                </div>
                <div class="form-moderno__campo">
                    <label class="form-moderno__etiqueta" for="imp_archivo">o subir archivo (.csv, .txt)</label>
                    <input class="form-moderno__control" type="file" id="imp_archivo" name="archivo" accept=".csv,.txt">
                </div>
                <p class="form-moderno__error asistente__error" data-importar-error role="alert"></p>
                <div data-importar-resultado hidden>
                    <p class="asistente__aviso" data-importar-resumen></p>
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th scope="col">Fila</th><th scope="col">Negocio</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead>
                            <tbody data-importar-filas></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal--moderno__pie">
                <button type="button" class="form-moderno__boton form-moderno__boton--secundario" data-modal-close="importarClientesModal">Cerrar</button>
                <button type="button" class="form-moderno__boton form-moderno__boton--secundario" data-importar-previa>Vista previa</button>
                <button type="button" class="form-moderno__boton form-moderno__boton--primario" data-importar-confirmar disabled>Importar clientes nuevos</button>
            </div>
        </form>
    </div>
</div>
