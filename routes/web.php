<?php

use App\Http\Controllers\Admin\AdsController;
use App\Http\Controllers\Admin\AdsConversionColumnaController;
use App\Http\Controllers\Admin\AdsCreativoController;
use App\Http\Controllers\Admin\AdsFaseController;
use App\Http\Controllers\Admin\AdsGrupoController;
use App\Http\Controllers\Admin\AdsEmbudoEtapaController;
use App\Http\Controllers\Admin\AdsGrupoKeywordController;
use App\Http\Controllers\Admin\AdsKeywordColumnaController;
use App\Http\Controllers\Admin\AdsMetricaController;
use App\Http\Controllers\Admin\AdsOptimizacionController;
use App\Http\Controllers\Admin\ArchivosController;
use App\Http\Controllers\Admin\AutomatizacionController;
use App\Http\Controllers\Admin\AutomatizacionFaseController;
use App\Http\Controllers\Admin\AutomatizacionFlujoController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\BugController;
use App\Http\Controllers\Admin\ClientesController;
use App\Http\Controllers\Admin\ComunicacionController;
use App\Http\Controllers\Admin\ConversionesController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesarrolloController;
use App\Http\Controllers\Admin\DocumentosController;
use App\Http\Controllers\Admin\FinanzasController;
use App\Http\Controllers\Admin\IntegracionesController;
use App\Http\Controllers\Admin\KeywordImportController;
use App\Http\Controllers\Admin\KeywordMedicionController;
use App\Http\Controllers\Admin\KeywordListasController;
use App\Http\Controllers\Admin\KeywordsController;
use App\Http\Controllers\Admin\ProyectoFaseController;
use App\Http\Controllers\Admin\QaController;
use App\Http\Controllers\Admin\ReporteGeneracionController;
use App\Http\Controllers\Admin\ReporteSeccionController;
use App\Http\Controllers\Admin\PropuestaController;
use App\Http\Controllers\Admin\ReportesController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\SeoBacklinkController;
use App\Http\Controllers\Admin\SeoMetricaMensualController;
use App\Http\Controllers\Admin\SeoContenidoController;
use App\Http\Controllers\Admin\SeoOnPageAccionController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SeoFaseController;
use App\Http\Controllers\Admin\SeoPosicionController;
use App\Http\Controllers\Admin\ServiciosController;
use App\Http\Controllers\Admin\TareaController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\PaginasController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('pages.index');
})->name('home');

/*
|--------------------------------------------------------------------------
| Public site routes
|--------------------------------------------------------------------------
|
| Content architecture for SEO: the site used to be a single indexable URL,
| so nothing could rank per service. Each service now has its own page.
|
*/

Route::get('/nosotros', [PaginasController::class, 'nosotros'])->name('nosotros');
Route::get('/contacto', [PaginasController::class, 'contacto'])->name('contacto');

Route::get('/servicios', [PaginasController::class, 'serviciosIndex'])->name('servicios.index');
Route::get('/servicios/{slug}', [PaginasController::class, 'serviciosShow'])->name('servicios.show');

Route::get('/terminos-y-condiciones', [PaginasController::class, 'terminos'])->name('legal.terminos');
Route::get('/aviso-de-privacidad', [PaginasController::class, 'privacidad'])->name('legal.privacidad');
Route::get('/politica-de-cookies', [PaginasController::class, 'cookies'])->name('legal.cookies');

/*
|--------------------------------------------------------------------------
| Blog
|--------------------------------------------------------------------------
|
| El orden importa: /blog/categoria/{cluster} tiene que ir ANTES de
| /blog/{slug}, o "categoria" se interpretaria como el slug de un articulo.
|
*/

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/categoria/{cluster}', [BlogController::class, 'cluster'])
    ->where('cluster', '[a-z0-9-]+')
    ->name('blog.cluster');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('blog.show');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Mapa del sitio en markdown para asistentes de IA (convencion llmstxt.org).
// Se sirve desde una ruta y no como archivo estatico para que no se desactualice
// cuando se da de alta un servicio o se publica un articulo.
Route::get('/llms.txt', [LlmsTxtController::class, 'index'])->name('llms');

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| Built out module by module. Now gated behind the `auth` middleware —
| routes/auth.php (Laravel Breeze) provides the `login` route it redirects
| guests to.
|
*/

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/exportar', [DashboardController::class, 'exportarReporte'])->name('dashboard.exportar');

    Route::prefix('blog')->name('blog.')->group(function () {
        Route::get('/', [AdminBlogController::class, 'index'])->name('index');
        Route::get('/nuevo', [AdminBlogController::class, 'create'])->name('create');
        Route::post('/', [AdminBlogController::class, 'store'])->name('store');
        Route::post('/previsualizar', [AdminBlogController::class, 'previsualizar'])->name('preview');
        Route::get('/{articulo}/editar', [AdminBlogController::class, 'edit'])->name('edit');
        Route::put('/{articulo}', [AdminBlogController::class, 'update'])->name('update');
        Route::delete('/{articulo}', [AdminBlogController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [ClientesController::class, 'index'])->name('index');
        Route::post('/', [ClientesController::class, 'store'])->name('store');
        Route::put('/{cliente}', [ClientesController::class, 'update'])->name('update');
        Route::delete('/{cliente}', [ClientesController::class, 'destroy'])->name('destroy');

        Route::get('/{cliente}/integraciones', [IntegracionesController::class, 'clienteIndex'])->name('integraciones');
        Route::post('/{cliente}/integraciones/token', [IntegracionesController::class, 'regenerarToken'])->name('integraciones.token');
        Route::get('/{cliente}/clics', [IntegracionesController::class, 'clics'])->name('clics');
        Route::get('/{cliente}/conversiones', [IntegracionesController::class, 'conversiones'])->name('conversiones');
        Route::get('/{cliente}/conversiones/embudo', [IntegracionesController::class, 'conversionesEmbudo'])->name('conversiones.embudo');
        Route::post('/{cliente}/conversiones/exportar', [IntegracionesController::class, 'exportarCsv'])->name('conversiones.exportar');
        Route::post('/{cliente}/conversiones/exportar-excel', [IntegracionesController::class, 'exportarExcel'])->name('conversiones.exportar-excel');
        Route::post('/{cliente}/conversiones/{conversion}/etapa', [IntegracionesController::class, 'asignarEtapa'])->name('conversiones.etapa');
        Route::post('/{cliente}/clics/{clic}/asignar', [IntegracionesController::class, 'asignarCampana'])->name('clics.asignar');

        Route::post('/{cliente}/embudo/etapas', [AdsEmbudoEtapaController::class, 'store'])->name('embudo.etapas.store');
        Route::put('/embudo/etapas/{etapa}', [AdsEmbudoEtapaController::class, 'update'])->name('embudo.etapas.update');
        Route::delete('/embudo/etapas/{etapa}', [AdsEmbudoEtapaController::class, 'destroy'])->name('embudo.etapas.destroy');

        Route::get('/{cliente}', [ClientesController::class, 'show'])->name('show');
    });

    Route::prefix('servicios')->name('servicios.')->group(function () {
        Route::get('/', [ServiciosController::class, 'index'])->name('index');
        Route::post('/', [ServiciosController::class, 'store'])->name('store');
        Route::put('/{servicio}', [ServiciosController::class, 'update'])->name('update');
        Route::delete('/{servicio}', [ServiciosController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('seo')->name('seo.')->group(function () {
        Route::get('/', [SeoController::class, 'index'])->name('index');
        Route::post('/', [SeoController::class, 'store'])->name('store');
        Route::put('/{campana}', [SeoController::class, 'update'])->name('update');
        Route::delete('/{campana}', [SeoController::class, 'destroy'])->name('destroy');

        Route::post('/{campana}/fase/guardar', [SeoFaseController::class, 'guardar'])->name('fase.guardar');
        Route::post('/{campana}/fase/aprobar', [SeoFaseController::class, 'aprobar'])->name('fase.aprobar');
        Route::post('/{campana}/fase/retroceder', [SeoFaseController::class, 'retroceder'])->name('fase.retroceder');
        Route::post('/{campana}/fase/nuevo-ciclo', [SeoFaseController::class, 'nuevoCiclo'])->name('fase.nuevo-ciclo');
        Route::post('/{campana}/fase/cerrar', [SeoFaseController::class, 'cerrar'])->name('fase.cerrar');
        Route::post('/{campana}/fase/pausar', [SeoFaseController::class, 'pausar'])->name('fase.pausar');

        Route::post('/{campana}/posiciones', [SeoPosicionController::class, 'store'])->name('posiciones.store');
        Route::delete('/posiciones/{posicion}', [SeoPosicionController::class, 'destroy'])->name('posiciones.destroy');

        Route::post('/{campana}/backlinks', [SeoBacklinkController::class, 'store'])->name('backlinks.store');
        Route::delete('/backlinks/{backlink}', [SeoBacklinkController::class, 'destroy'])->name('backlinks.destroy');

        Route::post('/{campana}/contenido', [SeoContenidoController::class, 'store'])->name('contenido.store');
        Route::put('/contenido/{contenido}', [SeoContenidoController::class, 'update'])->name('contenido.update');
        Route::delete('/contenido/{contenido}', [SeoContenidoController::class, 'destroy'])->name('contenido.destroy');

        Route::post('/{campana}/onpage', [SeoOnPageAccionController::class, 'store'])->name('onpage.store');
        Route::put('/onpage/{accion}', [SeoOnPageAccionController::class, 'update'])->name('onpage.update');
        Route::delete('/onpage/{accion}', [SeoOnPageAccionController::class, 'destroy'])->name('onpage.destroy');

        Route::post('/{campana}/metricas-mensuales', [SeoMetricaMensualController::class, 'store'])->name('metricas-mensuales.store');
        Route::put('/metricas-mensuales/{metrica}', [SeoMetricaMensualController::class, 'update'])->name('metricas-mensuales.update');
        Route::delete('/metricas-mensuales/{metrica}', [SeoMetricaMensualController::class, 'destroy'])->name('metricas-mensuales.destroy');

        Route::get('/{campana}', [SeoController::class, 'show'])->name('show');
    });

    Route::prefix('automatizaciones')->name('automatizaciones.')->group(function () {
        Route::get('/', [AutomatizacionController::class, 'index'])->name('index');
        Route::get('/nueva', [AutomatizacionController::class, 'create'])->name('create');
        Route::post('/', [AutomatizacionController::class, 'store'])->name('store');
        Route::get('/{proyecto}/editar', [AutomatizacionController::class, 'edit'])->name('edit');
        Route::put('/{proyecto}', [AutomatizacionController::class, 'update'])->name('update');
        Route::delete('/{proyecto}', [AutomatizacionController::class, 'destroy'])->name('destroy');

        Route::post('/{proyecto}/fase/guardar', [AutomatizacionFaseController::class, 'guardar'])->name('fase.guardar');
        Route::post('/{proyecto}/fase/aprobar', [AutomatizacionFaseController::class, 'aprobar'])->name('fase.aprobar');
        Route::post('/{proyecto}/fase/retroceder', [AutomatizacionFaseController::class, 'retroceder'])->name('fase.retroceder');
        Route::post('/{proyecto}/fase/nuevo-ciclo', [AutomatizacionFaseController::class, 'nuevoCiclo'])->name('fase.nuevo-ciclo');
        Route::post('/{proyecto}/fase/cerrar', [AutomatizacionFaseController::class, 'cerrar'])->name('fase.cerrar');
        Route::post('/{proyecto}/fase/pausar', [AutomatizacionFaseController::class, 'pausar'])->name('fase.pausar');

        Route::post('/{proyecto}/flujos', [AutomatizacionFlujoController::class, 'store'])->name('flujos.store');
        Route::put('/flujos/{flujo}', [AutomatizacionFlujoController::class, 'update'])->name('flujos.update');
        Route::delete('/flujos/{flujo}', [AutomatizacionFlujoController::class, 'destroy'])->name('flujos.destroy');

        Route::get('/{proyecto}', [AutomatizacionController::class, 'show'])->name('show');
    });

    Route::prefix('keywords')->name('keywords.')->group(function () {
        Route::get('/', [KeywordsController::class, 'index'])->name('index');
        Route::post('/', [KeywordsController::class, 'store'])->name('store');
        Route::put('/{keyword}', [KeywordsController::class, 'update'])->name('update');
        Route::delete('/{keyword}', [KeywordsController::class, 'destroy'])->name('destroy');

        Route::prefix('listas')->name('listas.')->group(function () {
            Route::post('/', [KeywordListasController::class, 'store'])->name('store');
            Route::put('/{lista}', [KeywordListasController::class, 'update'])->name('update');
            Route::delete('/{lista}', [KeywordListasController::class, 'destroy'])->name('destroy');
            Route::post('/bulk-descartar', [KeywordListasController::class, 'bulkDescartar'])->name('bulk-descartar');
            Route::post('/{lista}/importar', [KeywordImportController::class, 'store'])->name('importar');

            // Histórico de posiciones: la ronda mensual de la lista, y la
            // corrección de una medición suelta.
            Route::get('/{lista}/mediciones', [KeywordMedicionController::class, 'index'])->name('mediciones.index');
            Route::post('/{lista}/mediciones', [KeywordMedicionController::class, 'store'])->name('mediciones.store');
            Route::put('/mediciones/{medicion}', [KeywordMedicionController::class, 'update'])->name('mediciones.update');
            Route::delete('/mediciones/{medicion}', [KeywordMedicionController::class, 'destroy'])->name('mediciones.destroy');
        });
    });

    Route::prefix('conversiones')->name('conversiones.')->group(function () {
        Route::get('/', [ConversionesController::class, 'index'])->name('index');
        Route::post('/exportar-excel', [ConversionesController::class, 'exportarExcel'])->name('exportar-excel');
        Route::post('/{conversion}/etapa', [ConversionesController::class, 'asignarEtapa'])->name('etapa');
        Route::put('/{conversion}', [ConversionesController::class, 'actualizar'])->name('actualizar');
        Route::post('/columnas', [AdsConversionColumnaController::class, 'store'])->name('columnas.store');
        Route::put('/columnas/{columna}', [AdsConversionColumnaController::class, 'update'])->name('columnas.update');
        Route::delete('/columnas/{columna}', [AdsConversionColumnaController::class, 'destroy'])->name('columnas.destroy');
    });

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReportesController::class, 'index'])->name('index');
        Route::post('/', [ReportesController::class, 'store'])->name('store');

        // Las secciones cuelgan del reporte y el grupo va con scopeBindings():
        // así Laravel resuelve {seccion} DENTRO del {reporte} de la URL y
        // devuelve 404 si no le pertenece. Colgando de la raíz, el binding
        // global dejaba que cualquier usuario autenticado reescribiera o
        // borrara la sección de un reporte de otro cliente con solo saber el id.
        Route::scopeBindings()->group(function () {
            Route::post('/{reporte}/secciones', [ReporteSeccionController::class, 'store'])->name('secciones.store');
            Route::post('/{reporte}/secciones/reordenar', [ReporteSeccionController::class, 'reordenar'])->name('secciones.reordenar');
            Route::put('/{reporte}/secciones/{seccion}', [ReporteSeccionController::class, 'update'])->name('secciones.update');
            Route::delete('/{reporte}/secciones/{seccion}', [ReporteSeccionController::class, 'destroy'])->name('secciones.destroy');
            Route::post('/{reporte}/secciones/{seccion}/pegar', [ReporteSeccionController::class, 'pegar'])->name('secciones.pegar');
        });

        Route::get('/{reporte}/preview', [ReporteGeneracionController::class, 'preview'])->name('preview');
        Route::post('/{reporte}/pdf', [ReporteGeneracionController::class, 'pdf'])->name('pdf');
        Route::post('/{reporte}/xlsx', [ReporteGeneracionController::class, 'xlsx'])->name('xlsx');

        Route::put('/{reporte}', [ReportesController::class, 'update'])->name('update');
        Route::delete('/{reporte}', [ReportesController::class, 'destroy'])->name('destroy');

        // Al final del grupo, como en seo/desarrollo: el comodín {reporte} se
        // traga cualquier ruta literal declarada después (/secciones/... se
        // resolvería como un reporte llamado "secciones").
        Route::get('/{reporte}', [ReportesController::class, 'show'])->name('show');
    });

    Route::prefix('propuestas')->name('propuestas.')->group(function () {
        Route::get('/', [PropuestaController::class, 'index'])->name('index');
        Route::post('/', [PropuestaController::class, 'store'])->name('store');
        Route::put('/{propuesta}', [PropuestaController::class, 'update'])->name('update');
        Route::delete('/{propuesta}', [PropuestaController::class, 'destroy'])->name('destroy');

        Route::patch('/{propuesta}/secciones/{seccion}', [PropuestaController::class, 'actualizarSeccion'])->name('secciones.actualizar');
        Route::post('/{propuesta}/sugerir-consultas', [PropuestaController::class, 'sugerirConsultas'])->name('sugerir-consultas');
        Route::get('/{propuesta}/preview', [PropuestaController::class, 'preview'])->name('preview');
        Route::post('/{propuesta}/pdf', [PropuestaController::class, 'generarPdf'])->name('pdf');

        // Al final del grupo, como en reportes/seo: el comodín {propuesta} se
        // traga cualquier ruta literal declarada después.
        Route::get('/{propuesta}', [PropuestaController::class, 'show'])->name('show');
    });

    Route::prefix('ads')->name('ads.')->group(function () {
        Route::get('/', [AdsController::class, 'index'])->name('index');
        Route::post('/', [AdsController::class, 'store'])->name('store');
        Route::put('/{campana}', [AdsController::class, 'update'])->name('update');
        Route::delete('/{campana}', [AdsController::class, 'destroy'])->name('destroy');

        Route::post('/{campana}/fase/guardar', [AdsFaseController::class, 'guardar'])->name('fase.guardar');
        Route::post('/{campana}/fase/aprobar', [AdsFaseController::class, 'aprobar'])->name('fase.aprobar');
        Route::post('/{campana}/fase/retroceder', [AdsFaseController::class, 'retroceder'])->name('fase.retroceder');
        Route::post('/{campana}/fase/nuevo-ciclo', [AdsFaseController::class, 'nuevoCiclo'])->name('fase.nuevo-ciclo');
        Route::post('/{campana}/fase/cerrar', [AdsFaseController::class, 'cerrar'])->name('fase.cerrar');
        Route::post('/{campana}/fase/pausar', [AdsFaseController::class, 'pausar'])->name('fase.pausar');

        Route::post('/{campana}/grupos', [AdsGrupoController::class, 'store'])->name('grupos.store');
        Route::put('/grupos/{grupo}', [AdsGrupoController::class, 'update'])->name('grupos.update');
        Route::delete('/grupos/{grupo}', [AdsGrupoController::class, 'destroy'])->name('grupos.destroy');

        Route::post('/grupos/{grupo}/keywords', [AdsGrupoKeywordController::class, 'store'])->name('grupos.keywords.store');
        Route::put('/grupos/keywords/{keyword}', [AdsGrupoKeywordController::class, 'update'])->name('grupos.keywords.update');
        Route::delete('/grupos/keywords/{keyword}', [AdsGrupoKeywordController::class, 'destroy'])->name('grupos.keywords.destroy');

        Route::post('/grupos/{grupo}/columnas', [AdsKeywordColumnaController::class, 'store'])->name('grupos.columnas.store');
        Route::put('/grupos/columnas/{columna}', [AdsKeywordColumnaController::class, 'update'])->name('grupos.columnas.update');
        Route::delete('/grupos/columnas/{columna}', [AdsKeywordColumnaController::class, 'destroy'])->name('grupos.columnas.destroy');

        Route::post('/{campana}/creativos', [AdsCreativoController::class, 'store'])->name('creativos.store');
        Route::put('/creativos/{creativo}', [AdsCreativoController::class, 'update'])->name('creativos.update');
        Route::delete('/creativos/{creativo}', [AdsCreativoController::class, 'destroy'])->name('creativos.destroy');

        Route::post('/{campana}/metricas', [AdsMetricaController::class, 'store'])->name('metricas.store');
        Route::put('/metricas/{metrica}', [AdsMetricaController::class, 'update'])->name('metricas.update');
        Route::delete('/metricas/{metrica}', [AdsMetricaController::class, 'destroy'])->name('metricas.destroy');

        Route::post('/{campana}/optimizaciones', [AdsOptimizacionController::class, 'store'])->name('optimizaciones.store');
        Route::put('/optimizaciones/{optimizacion}', [AdsOptimizacionController::class, 'update'])->name('optimizaciones.update');
        Route::delete('/optimizaciones/{optimizacion}', [AdsOptimizacionController::class, 'destroy'])->name('optimizaciones.destroy');

        Route::get('/{campana}', [AdsController::class, 'show'])->name('show');
    });

    Route::prefix('desarrollo')->name('desarrollo.')->group(function () {
        Route::get('/', [DesarrolloController::class, 'index'])->name('index');
        Route::post('/', [DesarrolloController::class, 'store'])->name('store');
        Route::put('/{proyecto}', [DesarrolloController::class, 'update'])->name('update');
        Route::delete('/{proyecto}', [DesarrolloController::class, 'destroy'])->name('destroy');

        Route::post('/{proyecto}/fase/guardar', [ProyectoFaseController::class, 'guardar'])->name('fase.guardar');
        Route::post('/{proyecto}/fase/aprobar', [ProyectoFaseController::class, 'aprobar'])->name('fase.aprobar');
        Route::post('/{proyecto}/fase/retroceder', [ProyectoFaseController::class, 'retroceder'])->name('fase.retroceder');

        Route::post('/{proyecto}/tareas', [TareaController::class, 'store'])->name('tareas.store');
        Route::put('/tareas/{tarea}', [TareaController::class, 'update'])->name('tareas.update');
        Route::delete('/tareas/{tarea}', [TareaController::class, 'destroy'])->name('tareas.destroy');

        Route::get('/bugs', [BugController::class, 'index'])->name('bugs.index');
        Route::post('/{proyecto}/bugs', [BugController::class, 'store'])->name('bugs.store');
        Route::put('/bugs/{bug}', [BugController::class, 'update'])->name('bugs.update');
        Route::delete('/bugs/{bug}', [BugController::class, 'destroy'])->name('bugs.destroy');

        Route::post('/{proyecto}/comunicaciones', [ComunicacionController::class, 'store'])->name('comunicaciones.store');
        Route::delete('/comunicaciones/{comunicacion}', [ComunicacionController::class, 'destroy'])->name('comunicaciones.destroy');

        Route::post('/{proyecto}/qa', [QaController::class, 'store'])->name('qa.store');
        Route::put('/qa/{qa}', [QaController::class, 'update'])->name('qa.update');
        Route::delete('/qa/{qa}', [QaController::class, 'destroy'])->name('qa.destroy');

        Route::get('/{proyecto}', [DesarrolloController::class, 'show'])->name('show');
    });

    Route::prefix('finanzas')->name('finanzas.')->group(function () {
        Route::get('/', [FinanzasController::class, 'index'])->name('index');
        Route::get('/exportar', [FinanzasController::class, 'exportar'])->name('exportar');
        Route::post('/', [FinanzasController::class, 'store'])->name('store');
        Route::put('/{finanza}', [FinanzasController::class, 'update'])->name('update');
        Route::delete('/{finanza}', [FinanzasController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('archivos')->name('archivos.')->group(function () {
        Route::get('/', [ArchivosController::class, 'index'])->name('index');
        Route::post('/', [ArchivosController::class, 'store'])->name('store');
        Route::get('/contratos/nuevo', [DocumentosController::class, 'createContrato'])->name('contratos.create');
        Route::post('/contratos/vista-previa', [DocumentosController::class, 'previewContrato'])->name('contratos.preview');
        Route::post('/contratos', [DocumentosController::class, 'storeContrato'])->name('contratos.store');
        Route::get('/propuestas/nuevo', [DocumentosController::class, 'createPropuesta'])->name('propuestas.create');
        Route::post('/propuestas/vista-previa', [DocumentosController::class, 'previewPropuesta'])->name('propuestas.preview');
        Route::post('/propuestas', [DocumentosController::class, 'storePropuesta'])->name('propuestas.store');
        Route::get('/{archivo}/descargar', [ArchivosController::class, 'download'])->name('download');
        Route::delete('/{archivo}', [ArchivosController::class, 'destroy'])->name('destroy');
    });

    Route::get('/integraciones', [IntegracionesController::class, 'index'])->name('integraciones.index');

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RolesController::class, 'index'])->name('index');
        Route::post('/', [RolesController::class, 'store'])->name('store');
        Route::put('/{role}', [RolesController::class, 'update'])->name('update');
        Route::delete('/{role}', [RolesController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsersController::class, 'index'])->name('index');
        Route::post('/', [UsersController::class, 'store'])->name('store');
        Route::put('/{user}', [UsersController::class, 'update'])->name('update');
        Route::post('/{user}/desactivar', [UsersController::class, 'deactivate'])->name('deactivate');
    });
});

require __DIR__.'/auth.php';
