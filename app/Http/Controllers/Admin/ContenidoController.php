<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\ElementoContenido;
use App\Support\ClienteActivo;
use App\Support\Contenido\ArbolContenido;
use App\Support\Contenido\TablaContenido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Explorador del módulo «Contenido»: árbol de carpetas, documentos, tablas y
 * archivos del cliente activo. Crear, renombrar/mover, borrar, subir y
 * descargar. La edición de documentos y tablas vive en sus propios controladores.
 */
class ContenidoController extends ContenidoBaseController
{
    /** Mismos formatos que Archivos (ver App\Support\Reglas\Archivos), con un tope menor: aquí es material de trabajo, no entregables. */
    private const MIMES_ARCHIVO = 'pdf,zip,rar,doc,docx,xls,xlsx,csv,ppt,pptx,png,jpg,jpeg,gif,svg,fig,txt';
    private const MAX_ARCHIVO_KB = 51200;

    public function index(Request $request): View
    {
        $cliente = ClienteActivo::modelo();

        if (! $cliente) {
            return view('admin.contenido.index', ['pageTitle' => 'Contenido', 'cliente' => null]);
        }

        // Carpetas del cliente completas: sirven para migas de pan, destinos de mover y rutas de búsqueda.
        $carpetas = ElementoContenido::query()
            ->where('cliente_id', $cliente->id)
            ->where('tipo', TipoElementoContenido::Carpeta->value)
            ->orderBy('nombre')
            ->get(['id', 'cliente_id', 'parent_id', 'tipo', 'nombre'])
            ->keyBy('id');

        $busqueda = trim((string) $request->query('q', ''));
        $actual = null;

        if ($busqueda !== '') {
            $like = '%'.addcslashes($busqueda, '%_\\').'%';
            $elementos = ElementoContenido::query()
                ->where('cliente_id', $cliente->id)
                ->where('nombre', 'like', $like)
                ->orderBy('nombre')
                ->limit(200)
                ->get();
        } else {
            $carpetaId = $request->integer('carpeta') ?: null;
            $actual = $carpetaId ? $carpetas->get($carpetaId) : null;
            abort_if($carpetaId && ! $actual, 404);

            $elementos = ElementoContenido::query()
                ->where('cliente_id', $cliente->id)
                ->where('parent_id', $carpetaId)
                // Carpetas primero y luego por nombre: el orden habitual de un explorador.
                ->orderByRaw("tipo = 'carpeta' desc")
                ->orderBy('nombre')
                ->get();
        }

        $filas = $elementos->map(fn (ElementoContenido $e) => $this->fila($e, $carpetas))->values();

        return view('admin.contenido.index', [
            'pageTitle' => 'Contenido',
            'cliente' => $cliente,
            'migas' => ArbolContenido::cadena($actual, $carpetas),
            'carpetaActual' => $actual,
            'busqueda' => $busqueda,
            'filas' => $filas,
            'destinos' => $carpetas->map(fn ($c) => [
                'id' => $c->id,
                'ruta' => collect(ArbolContenido::cadena($c, $carpetas))->pluck('nombre')->implode(' / '),
            ])->sortBy('ruta')->values(),
            'tipos' => [
                'extensiones' => '.'.str_replace(',', ',.', self::MIMES_ARCHIVO),
                'maxMb' => self::MAX_ARCHIVO_KB / 1024,
            ],
        ]);
    }

    /** Crea una carpeta, un documento o una tabla (con la plantilla de calendario si se pide). */
    public function store(Request $request): JsonResponse
    {
        $clienteId = $this->clienteActivoObligatorio();

        $data = $request->validate([
            'tipo' => ['required', Rule::in(['carpeta', 'documento', 'tabla'])],
            'nombre' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'plantilla' => ['nullable', Rule::in(['calendario'])],
        ]);

        if ($error = ArbolContenido::errorDePadre($clienteId, $data['parent_id'] ?? null)) {
            return response()->json(['message' => $error, 'errors' => ['parent_id' => [$error]]], 422);
        }

        $contenido = match ($data['tipo']) {
            'documento' => '',
            'tabla' => json_encode(
                ($data['plantilla'] ?? null) === 'calendario' ? TablaContenido::plantillaCalendario() : TablaContenido::vacia(),
                JSON_UNESCAPED_UNICODE
            ),
            default => null,
        };

        $elemento = ElementoContenido::create([
            'cliente_id' => $clienteId,
            'parent_id' => $data['parent_id'] ?? null,
            'tipo' => $data['tipo'],
            'nombre' => $data['nombre'],
            'contenido' => $contenido,
        ]);

        return response()->json([
            'id' => $elemento->id,
            'tipo' => $elemento->tipo->value,
            'url' => route('admin.contenido.show', $elemento),
        ], 201);
    }

    /** Sube un archivo al disco privado (igual que Archivos: no es público, se baja por descarga autenticada). */
    public function subir(Request $request): JsonResponse
    {
        $clienteId = $this->clienteActivoObligatorio();

        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:'.self::MAX_ARCHIVO_KB, 'mimes:'.self::MIMES_ARCHIVO],
            'parent_id' => ['nullable', 'integer'],
        ]);

        if ($error = ArbolContenido::errorDePadre($clienteId, $data['parent_id'] ?? null)) {
            return response()->json(['message' => $error, 'errors' => ['parent_id' => [$error]]], 422);
        }

        $archivo = $request->file('archivo');
        $ruta = $archivo->store("clientes/{$clienteId}/contenido", 'local');

        $elemento = ElementoContenido::create([
            'cliente_id' => $clienteId,
            'parent_id' => $data['parent_id'] ?? null,
            'tipo' => TipoElementoContenido::Archivo->value,
            'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'archivo_path' => $ruta,
        ]);

        return response()->json(['id' => $elemento->id, 'tipo' => 'archivo'], 201);
    }

    /** Renombra y/o mueve. Solo toca lo que viene en la petición. */
    public function actualizar(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->delClienteActivo($elemento);

        $data = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        if ($request->exists('parent_id')) {
            $destino = $data['parent_id'] ?? null;

            if ($error = ArbolContenido::errorDePadre($elemento->cliente_id, $destino, $elemento)) {
                return response()->json(['message' => $error, 'errors' => ['parent_id' => [$error]]], 422);
            }

            $elemento->parent_id = $destino;
        }

        if (isset($data['nombre'])) {
            $elemento->nombre = $data['nombre'];
        }

        $elemento->save();

        return response()->json(['id' => $elemento->id]);
    }

    /**
     * Borra el elemento y todo lo que cuelga de él. Los archivos físicos se
     * eliminan de verdad: no hay papelera en pantalla desde la que restaurarlos,
     * y dejarlos sería ocupar disco sin que nadie pueda llegar a ellos.
     */
    public function destroy(ElementoContenido $elemento): JsonResponse
    {
        $this->delClienteActivo($elemento);

        $ids = array_merge([$elemento->id], ArbolContenido::idsDescendientes($elemento));

        $rutas = ElementoContenido::query()->whereIn('id', $ids)->whereNotNull('archivo_path')->pluck('archivo_path');
        foreach ($rutas as $ruta) {
            Storage::disk('local')->delete($ruta);
        }

        ElementoContenido::query()->whereIn('id', $ids)->delete();

        return response()->json(['deleted' => true, 'cantidad' => count($ids)]);
    }

    public function descargar(ElementoContenido $elemento): StreamedResponse|RedirectResponse
    {
        $this->delClienteActivo($elemento);
        abort_unless($elemento->tipo === TipoElementoContenido::Archivo, 404);

        if (! $elemento->archivo_path || ! Storage::disk('local')->exists($elemento->archivo_path)) {
            return back()->withErrors(['archivo' => 'El archivo ya no está disponible en el servidor.']);
        }

        return Storage::disk('local')->download($elemento->archivo_path, $elemento->nombre);
    }

    /** Abre un elemento según su tipo. */
    public function show(ElementoContenido $elemento): View|RedirectResponse
    {
        $this->delClienteActivo($elemento);

        return match ($elemento->tipo) {
            TipoElementoContenido::Carpeta => redirect()->route('admin.contenido.index', ['carpeta' => $elemento->id]),
            TipoElementoContenido::Archivo => redirect()->route('admin.contenido.descargar', $elemento),
            TipoElementoContenido::Documento => $this->vistaEditor('admin.contenido.documento', $elemento, [
                'urlGuardar' => route('admin.contenido.documento.guardar', $elemento),
                'urlPrevia' => route('admin.contenido.previsualizar'),
            ]),
            TipoElementoContenido::Tabla => $this->vistaEditor('admin.contenido.tabla', $elemento, [
                'datos' => TablaContenido::decodificar($elemento->contenido),
                'urlGuardar' => route('admin.contenido.tabla.guardar', $elemento),
                'urlImportar' => route('admin.contenido.tabla.importar', $elemento),
                'urlExportar' => route('admin.contenido.tabla.exportar', $elemento),
            ]),
        };
    }

    private function vistaEditor(string $vista, ElementoContenido $elemento, array $extra): View
    {
        $carpetas = ElementoContenido::query()
            ->where('cliente_id', $elemento->cliente_id)
            ->where('tipo', TipoElementoContenido::Carpeta->value)
            ->get(['id', 'cliente_id', 'parent_id', 'tipo', 'nombre'])
            ->keyBy('id');

        $padre = $elemento->parent_id ? $carpetas->get($elemento->parent_id) : null;

        return view($vista, [
            'pageTitle' => $elemento->nombre,
            'elemento' => $elemento,
            'migas' => ArbolContenido::cadena($padre, $carpetas),
            'urlRenombrar' => route('admin.contenido.actualizar', $elemento),
            'urlVolver' => route('admin.contenido.index', array_filter(['carpeta' => $elemento->parent_id])),
        ] + $extra);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ElementoContenido>  $carpetas
     */
    private function fila(ElementoContenido $e, $carpetas): array
    {
        $tamano = null;
        if ($e->tipo === TipoElementoContenido::Archivo && $e->archivo_path && Storage::disk('local')->exists($e->archivo_path)) {
            $tamano = Storage::disk('local')->size($e->archivo_path);
        }

        $padre = $e->parent_id ? $carpetas->get($e->parent_id) : null;

        return [
            'id' => $e->id,
            'tipo' => $e->tipo->value,
            'tipo_label' => $e->tipo->label(),
            'icono' => $e->tipo->icono(),
            'nombre' => $e->nombre,
            'parent_id' => $e->parent_id,
            'ruta' => $padre ? collect(ArbolContenido::cadena($padre, $carpetas))->pluck('nombre')->implode(' / ') : null,
            'actualizado' => $e->updated_at?->format('Y-m-d H:i'),
            'tamano' => $tamano === null ? null : ($tamano >= 1048576 ? round($tamano / 1048576, 1).' MB' : max(1, (int) round($tamano / 1024)).' KB'),
            'abrir' => $e->tipo === TipoElementoContenido::Carpeta
                ? route('admin.contenido.index', ['carpeta' => $e->id])
                : ($e->tipo === TipoElementoContenido::Archivo ? route('admin.contenido.descargar', $e) : route('admin.contenido.show', $e)),
            'url_actualizar' => route('admin.contenido.actualizar', $e),
            'url_borrar' => route('admin.contenido.destroy', $e),
        ];
    }
}
